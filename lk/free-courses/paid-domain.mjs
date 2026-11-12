import {getCourse,id,findEnrollment} from './domain.mjs?v=20261112-courses-6';
import {isPaidCourse} from './paid-catalog.mjs?v=20261112-courses-6';

export const paymentLabels={pending:'Ждёт оплаты',paid:'Оплачен',failed:'Оплата не прошла',review:'Нужна сверка',refunded:'Возврат подтверждён'};
// A local simulation of the server's verified event contract, never a live payment receiver.
export function applyDemoPayment(state,event) {
  if(event.verified!==true)throw new Error('Неподтверждённое событие не открывает доступ.');
  const order=state.orders.find(o=>o.id===event.orderId);
  if(!order||!isPaidCourse(getCourse(order.courseId)))throw new Error('Неизвестный заказ.');
  if(!event.id||!['paid','failed','refunded'].includes(event.status))throw new Error('Некорректное событие.');
  if(state.paymentEvents.some(e=>e.id===event.id))return {duplicate:true,order};
  const log=text=>state.audit.push({id:id('audit'),orderId:order.id,userId:order.userId,at:state.now,text});
  const issue=event.courseId!==order.courseId||event.currency!==order.currency||event.amount!==order.amount;
  const previous=state.paymentEvents.find(e=>e.transactionId===event.transactionId&&e.orderId!==order.id);
  if(issue||!event.transactionId||previous||state.orders.some(o=>o.id!==order.id&&o.transactionId===event.transactionId)){
    if(!['paid','refunded'].includes(order.status))order.status='review';
    order.issue='Не совпали курс, сумма, валюта или идентификатор платежа';
    log('Событие отправлено на сверку. Доступ не выдан.');
    state.paymentEvents.push({...event,result:'review'});return {review:true,order};
  }
  if(event.status==='paid'){
    if(['paid','refunded'].includes(order.status)){
      state.paymentEvents.push({...event,result:'ignored'});return {duplicate:true,order};
    }
    const time=Date.parse(event.paidAt);
    if(!Number.isFinite(time)||time>Date.parse(state.now)||time<Date.parse(order.createdAt))throw new Error('Некорректное время оплаты.');
    if(findEnrollment(state,order.userId,order.courseId)){
      order.status='review';order.issue='По этому курсу уже есть зачисление: проверить повторную покупку или продление';
      state.paymentEvents.push({...event,result:'review'});log(order.issue);return {review:true,order};
    }
    order.status='paid';order.paidAt=event.paidAt;order.transactionId=event.transactionId;delete order.issue;
    const e={id:id('paid-enrollment'),orderId:order.id,userId:order.userId,courseId:order.courseId,start:event.paidAt,until:new Date(time+order.accessDays*86400000).toISOString(),done:[],lastVisit:null,source:'Оплата — демо',accessStatus:'active'};
    state.enrollments.push(e);
    state.mail.push({id:id('mail'),enrollmentId:e.id,status:'prepared',attempts:1,at:state.now});
    log('Подтверждена полная оплата. Открыт доступ на 90 дней со дня оплаты. Письмо подготовлено в демо.');
  }else if(event.status==='refunded'){
    if(order.status!=='paid'||event.transactionId!==order.transactionId)throw new Error('Возврат должен относиться к подтверждённому платежу.');
    order.status='refunded';order.refundedAt=state.now;
    state.enrollments.filter(e=>e.orderId===order.id).forEach(e=>{e.accessStatus='revoked';});
    log('Демо: подтверждён полный возврат. Доступ по этому заказу закрыт, прогресс сохранён.');
  }else if(!['paid','refunded'].includes(order.status)){
    order.status='failed';log('Оплата не прошла. Доступ не выдан.');
  }
  state.paymentEvents.push({...event,result:order.status});
  return {order};
}
