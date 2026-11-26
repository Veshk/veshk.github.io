import test from 'node:test';
import {active} from '../free-courses/domain.mjs';
import assert from 'node:assert/strict';
import {createRequire} from 'node:module';
import {grantCourse,courseAccesses,readCourses,writeCourses,courses,isPaidCourse} from '../student-learning-access.mjs';
const require=createRequire(import.meta.url),{create}=require('../student-directory.js');
const storage=()=>{const data={};return {getItem:k=>data[k],setItem:(k,v)=>data[k]=v};};
test('individual group access resolves pending request and persists without duplicates',()=>{
 const requests=[{id:1,name:'Test',email:'test@example.test',group:'g',status:'pending'}],accesses={};
 const options={groups:{g:{end:'2027-03-01'}},sampleGroups:{},requests,accesses,closes:g=>g.end,canManageGroup:()=>true,today:()=> '2026-11-26'};
 const d=create(options);d.grant('request-1','g','2026-12-01');d.grant('request-1','g','2026-12-02');
 assert.equal(requests.length,1);assert.equal(requests[0].status,'approved');
 const reloaded=create({...options,accesses:JSON.parse(JSON.stringify(accesses)),today:()=> '2026-12-03'});
 assert.equal(reloaded.untilFor(reloaded.rows()[0],'g'),'2026-12-02');assert.equal(reloaded.rows()[0].status,'closed');
 assert.throws(()=>d.grant('request-1','g','2026-02-30'));assert.throws(()=>create({...options,canManageGroup:()=>false}).grant('request-1','g','2026-12-20'));
});
test('course access reuses existing account and progress, with no payment or mail',()=>{
 const s=storage(),state=readCourses(s),person=state.people[0],course=courses.find(isPaidCourse),now='2026-11-26T07:00:00Z';
 const mails=state.mail.length,people=state.people.length;
 const a=grantCourse(state,person,course.id,'2026-12-15',true,now);a.done=[1];a.notes={1:'keep'};
 const b=grantCourse(state,person,course.id,'2026-12-01',true,now);
 assert.equal(a.id,b.id);assert.deepEqual(b.done,[1]);assert.equal(b.notes[1],'keep');assert.equal(state.people.length,people);assert.equal(state.mail.length,mails);assert.equal(b.until,'2026-12-01T18:59:59.999Z');
 writeCourses(state,s);assert.equal(courseAccesses(readCourses(s),person.email).filter(e=>e.courseId===course.id).length,1);
 assert.throws(()=>grantCourse(state,person,course.id,'2026-12-15',false,now));assert.throws(()=>grantCourse(state,person,course.id,'2026-02-30',true,now));
});
test('a scheduled period stays closed until the first day',()=>{
 const accesses={},requests=[{id:1,name:'Test',email:'test@example.test',group:'g',status:'pending'}];
 const base={groups:{g:{end:'2027-03-01'}},sampleGroups:{},requests,accesses,closes:g=>g.end,canManageGroup:()=>true};
 const d=create({...base,today:()=> '2026-11-26'});d.grant('request-1','g','2026-12-02','2026-12-01');assert.equal(d.rows()[0].status,'scheduled');
 const later=create({...base,today:()=> '2026-12-01'});assert.equal(later.rows()[0].status,'active');
 assert.throws(()=>d.grant('request-1','g','2026-12-01','2026-12-02'));
 const s=readCourses(storage()),a=grantCourse(s,s.people[0],courses[0].id,'2026-12-02',true,'2026-11-26T07:00:00Z','2026-12-01');assert.equal(a.start,'2026-11-30T19:00:00.000Z');assert.equal(active({...s,now:'2026-11-30T18:59:59Z'},a),false);assert.equal(active({...s,now:'2026-11-30T19:00:00Z'},a),true);
});
