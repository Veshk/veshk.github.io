import { courses, seed } from './data.mjs';
export { courses, seed };
export const normalizeEmail = value => String(value).trim().toLowerCase();
export const id = prefix => `${prefix}-${globalThis.crypto.randomUUID()}`;
export const getCourse = courseId => courses.find(c => c.id === courseId);
export const active = (state, enrollment) => Boolean(enrollment && Date.parse(enrollment.until) > Date.parse(state.now));
export const pending = (state, enrollmentId) => state.requests.find(r => r.enrollmentId === enrollmentId && r.status === 'pending');
export const findEnrollment = (state, userId, courseId) => state.enrollments.find(e => e.userId === userId && e.courseId === courseId);
export const percent = enrollment => Math.round(enrollment.done.length / getCourse(enrollment.courseId).lessons.length * 100);
export const shortDate = value => new Intl.DateTimeFormat('ru-RU',{day:'numeric',month:'short',timeZone:'Asia/Yekaterinburg'}).format(new Date(value));
export const fullDate = value => new Intl.DateTimeFormat('ru-RU',{day:'numeric',month:'long',year:'numeric',hour:'2-digit',minute:'2-digit',timeZone:'Asia/Yekaterinburg'}).format(new Date(value));
// A calendar month in the school's fixed UTC+5 zone, clamped at month end.
export function addMonth(value) {
  const d = new Date(Date.parse(value) + 5 * 3600000);
  const day = d.getUTCDate();
  d.setUTCDate(1); d.setUTCMonth(d.getUTCMonth() + 1);
  const last = new Date(Date.UTC(d.getUTCFullYear(),d.getUTCMonth()+1,0)).getUTCDate();
  d.setUTCDate(Math.min(day,last));
  return new Date(d.getTime() - 5 * 3600000).toISOString();
}
export function suggestedExtension(state, enrollment) {
  return new Date(Math.max(Date.parse(state.now), Date.parse(enrollment.until)) + 14 * 86400000 + 5 * 3600000).toISOString().slice(0,10);
}
export function register(state, {name,email,job,courseId,consent,marketing=false,submissionId=id('submission')}) {
  email = normalizeEmail(email);
  if (!getCourse(courseId) || !name.trim() || !job.trim() || !/^[^\s@]+@example\.test$/.test(email) || !consent) throw new Error('Заполните имя, должность и тестовый email вида name@example.test. Подтвердите согласие.');
  const repeat = state.submissions.find(s => s.id === submissionId);
  if (repeat) return repeat;
  let person = state.people.find(p => p.email === email);
  const isNew = !person;
  if (!person) { person = {id:id('person'),name:name.trim(),email,job:job.trim(),paid:false}; state.people.push(person); }
  let enrollment = findEnrollment(state,person.id,courseId);
  const duplicate = Boolean(enrollment);
  if (!enrollment) {
    enrollment = {id:id('enrollment'),userId:person.id,courseId,start:state.now,until:addMonth(state.now),done:[],lastVisit:null,source:'Лендинг',consentAt:state.now,marketing:Boolean(marketing)};
    state.enrollments.push(enrollment);
  }
  const receipt = {id:submissionId,userId:person.id,enrollmentId:enrollment.id,isNew,duplicate,at:state.now};
  state.submissions.push(receipt);
  state.mail.push({id:id('mail'),enrollmentId:enrollment.id,status:'prepared',attempts:1,at:state.now});
  state.audit.push({id:id('audit'),enrollmentId:enrollment.id,at:state.now,text:duplicate?'Повторная заявка: срок и прогресс сохранены.':'Зачисление с лендинга. Доступ на календарный месяц.'});
  return receipt;
}
export function requestExtension(state,enrollmentId,reason) {
  const enrollment = state.enrollments.find(e=>e.id===enrollmentId && e.userId===state.userId);
  if (!enrollment || !String(reason).trim()) throw new Error('Укажите причину продления.');
  if (pending(state,enrollmentId)) return false;
  state.requests.push({id:id('request'),enrollmentId,reason:String(reason).trim(),status:'pending',at:state.now});
  return true;
}
export function extend(state,enrollmentId,date,reason) {
  const e = state.enrollments.find(item=>item.id===enrollmentId);
  const until = new Date(`${date}T23:59:59.999+05:00`);
  if (!e || !/^\d{4}-\d{2}-\d{2}$/.test(date) || !Number.isFinite(until.getTime()) || until.getTime() <= Math.max(Date.parse(state.now),Date.parse(e.until)) || !reason.trim()) throw new Error('Выберите дату позже текущего срока и сегодняшнего дня. Укажите причину.');
  if (new Date(until.getTime()+5*3600000).toISOString().slice(0,10)!==date) throw new Error('Укажите существующую календарную дату.');
  const oldUntil = e.until; e.until = until.toISOString();
  state.requests.filter(r=>r.enrollmentId===e.id&&r.status==='pending').forEach(r=>{r.status='approved';r.resolvedAt=state.now;});
  state.audit.push({id:id('audit'),enrollmentId:e.id,at:state.now,text:`Администратор продлил доступ: ${fullDate(oldUntil)} → ${fullDate(e.until)}. Причина: ${reason.trim()}`});
  state.mail.push({id:id('mail'),enrollmentId:e.id,status:'prepared',attempts:1,at:state.now});
  return e;
}
export function retryMail(state,mailId) {
  const mail = state.mail.find(m=>m.id===mailId);
  if (!mail || mail.status!=='error') return false;
  mail.status='prepared';mail.attempts++;mail.at=state.now;
  state.audit.push({id:id('audit'),enrollmentId:mail.enrollmentId,at:state.now,text:'Повтор письма подготовлен. Срок доступа не изменён.'});
  return true;
}
export function markLesson(state,enrollmentId,index) {
  const e=state.enrollments.find(e=>e.id===enrollmentId && e.userId===state.userId);
  if (!active(state,e) || !Number.isInteger(index) || index<0 || index>=getCourse(e.courseId).lessons.length) throw new Error('Урок недоступен.');
  e.done=e.done.includes(index)?e.done.filter(i=>i!==index):[...e.done,index].sort((a,b)=>a-b);
  e.lastVisit=state.now;
}
