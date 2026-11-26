import {courses,seed} from './free-courses/data.mjs?v=20261126-public-safe-1';
import {isPaidCourse} from './free-courses/paid-catalog.mjs?v=20261126-public-safe-1';
export {courses,isPaidCourse};
const key='vesh.free-courses.public.v2';
export function readCourses(storage=localStorage){
  const saved=JSON.parse(storage.getItem(key)||'null');
  return saved?.version===1&&Array.isArray(saved.enrollments)?saved:seed();
}
export const courseAccesses=(state,email)=>state.enrollments.filter(e=>state.people.find(p=>p.id===e.userId)?.email.toLowerCase()===email.toLowerCase());
export function grantCourse(state,person,courseId,until,senior,now=new Date().toISOString(),from=new Intl.DateTimeFormat('sv-SE',{timeZone:'Asia/Yekaterinburg'}).format(new Date(now))){
  const end=new Date(until+'T23:59:59.999+05:00');
  const start=new Date(from+'T00:00:00+05:00');
  if(!/^\d{4}-\d{2}-\d{2}$/.test(from)||!Number.isFinite(start.getTime())||new Date(start.getTime()+5*3600000).toISOString().slice(0,10)!==from||start>end)throw Error('Дата начала должна быть не позже даты окончания');
  if(!senior||!courses.some(c=>c.id===courseId))throw Error('Нет прав на этот курс');
  if(!/^\d{4}-\d{2}-\d{2}$/.test(until)||!Number.isFinite(end.getTime())||new Date(end.getTime()+5*3600000).toISOString().slice(0,10)!==until||end.getTime()<Date.parse(now))throw Error('Выберите сегодняшнюю или будущую дату');
  let user=state.people.find(p=>p.email.toLowerCase()===person.email.toLowerCase());
  if(!user){user={id:'manual-'+crypto.randomUUID(),name:person.name,email:person.email,job:'',paid:false};state.people.push(user);}
  let access=state.enrollments.find(e=>e.userId===user.id&&e.courseId===courseId);
  if(!access){access={id:'manual-'+crypto.randomUUID(),userId:user.id,courseId,start:now,done:[],lastVisit:null,source:'Администратор'};state.enrollments.push(access);}
  access.start=start.toISOString();access.until=end.toISOString();access.accessStatus='active';
  state.audit.push({id:'audit-'+crypto.randomUUID(),enrollmentId:access.id,at:now,text:'Индивидуальный доступ до '+until});
  return access;
}
export function writeCourses(state,storage=localStorage){storage.setItem(key,JSON.stringify(state));}
