const test = require('node:test');
const assert = require('node:assert/strict');
const {create} = require('../student-directory.js');

test('counts unique enrolled accounts, keeps expired enrollments, and excludes pending requests', () => {
  const requests = [
    {id:1,name:'New',email:'new@example.test',status:'pending',group:'a'},
    {id:2,name:'Same',email:' STUDENT-1@vesh.test ',status:'approved',group:'a'},
    {id:3,name:'Same',email:'student-1@vesh.test',status:'approved',group:'b'},
    {id:4,name:'Gone',email:'gone@example.test',status:'approved',group:'missing'},
  ];
  const directory=create({groups:{a:{until:'2026-11-04'},b:{until:'2027-01-01'},empty:{until:'2027-01-01'}},sampleGroups:{a:{}},requests,closes:g=>g.until,today:()=> '2026-11-06'});
  assert.equal(directory.participants('a').length,1);
  assert.equal(directory.participants('b').length,1);
  assert.equal(directory.participants('empty').length,0);
  const shared=directory.participants('a')[0];
  assert.deepEqual(shared.groups,['a','b']);
  assert.equal(shared.status,'active');
  assert.equal(directory.statusFor(shared,'a'),'closed');
  assert.equal(directory.statusFor(shared,'b'),'active');
  requests[0].status='approved';
  assert.equal(directory.participants('a').length,2);
  assert.equal(directory.participants('a').filter(p=>p.email==='new@example.test').length,1);
});

test('access stays open through the end date in the current group', () => {
  const directory=create({groups:{a:{until:'2026-11-06'}},sampleGroups:{a:{}},requests:[],closes:g=>g.until,today:()=> '2026-11-06'});
  assert.equal(directory.participants('a')[0].status,'active');
});

test('the demo student studies in both populated groups without duplicate membership', () => {
  const groups={mba:{until:'2027-05-07'},other:{until:'2027-01-01'},kd26:{until:'2026-12-09'}};
  const directory=create({groups,sampleGroups:groups,requests:[],closes:g=>g.until,today:()=> '2026-11-11'});
  const student=directory.rows().find(person=>person.id==='student-0');
  assert.deepEqual(student.groups,['kd26','mba']);
  assert.equal(directory.participants('kd26').filter(person=>person.id==='student-0').length,1);
  assert.equal(directory.participants('mba').filter(person=>person.id==='student-0').length,1);
  assert.equal(directory.statusFor(student,'kd26'),'active');
  assert.deepEqual(directory.participants('other').map(person=>person.id),['student-1']);
});

test('pending membership is visible without granting access and follows reassignment and decisions', () => {
  const requests=[{id:1,name:'Same',email:'student-1@vesh.test',status:'pending',group:'b'}];
  const d=create({groups:{a:{until:'2027-01-01'},b:{until:'2027-01-01'},c:{until:'2027-01-01'}},sampleGroups:{a:{}},requests,closes:g=>g.until,today:()=> '2026-11-16'});
  assert.equal(d.rows().length,1);
  assert.equal(d.applicants('b').length,1);
  assert.equal(d.participants('b').length,0);
  assert.equal(d.belongsTo(d.rows()[0],'b'),true);
  assert.equal(d.statusFor(d.rows()[0],'b'),'waiting');
  assert.equal(d.statusFor(d.rows()[0]),'active');
  requests[0].group='c';
  assert.equal(d.applicants('b').length,0);
  assert.equal(d.applicants('c').length,1);
  requests[0].status='rejected';
  assert.equal(d.applicants('c').length,0);
  requests[0].status='approved';
  assert.equal(d.participants('c').length,1);
  assert.equal(d.rows().length,1);
  assert.equal(d.rows()[0].pendingApplications.length,0);
});

test('unassigned applications follow viewer permissions without hiding the person', () => {
  const options={groups:{},sampleGroups:{},requests:[{id:1,name:'Waiting',email:'waiting@example.test',status:'pending',group:''}],closes:g=>g.until};
  const hidden=create({...options,canViewRequest:r=>!!r.group});
  assert.equal(hidden.rows().length,1);
  assert.equal(hidden.rows()[0].pendingApplications.length,0);
  const visible=create({...options,canViewRequest:()=>true});
  assert.equal(visible.rows()[0].pendingApplications.length,1);
  assert.equal(visible.rows()[0].status,'waiting');
});
