// Public programme metadata only. Paid media and source-system identifiers stay private.
const catalogue = [
  {id:'dashboards',short:'Дашборды в Excel',title:'Динамические дашборды в Excel для руководителя',category:'Работа с данными',color:'blue',expert:'Татьяна Каримова',source:'https://edu.veshk.ru/online-course-dashbordy',
    modules:[['Введение в дашборды и инструменты Excel',1],['Отчёт по продажам: работа с одной выгрузкой',4],['Управленческий баланс: объединение двух таблиц',4],['Отчёт о прибылях и убытках: сбор данных из папок',3],['Отчёт о простоях оборудования: сбор данных с листов',4]],
    minutes:[7,5,16,18,16,13,22,15,11,16,13,33,7,8,27,8]},
  {id:'market-strategy',short:'Рыночная стратегия',title:'Разработка рыночной стратегии: как бизнесу найти точки роста',category:'Маркетинг',color:'rose',expert:'Юлия Рублевская',source:'https://edu.veshk.ru/online-course-strategiya',
    modules:[['Новые подходы к разработке стратегии',3],['Информация для разработки стратегии',1],['Причины низких продаж и маркетинговые воронки',1],['Потребности и удовлетворённость клиентов',3],['Удержание клиентов и воспринимаемое качество',1],['Сегментация потребителей',4]],
    minutes:[8,13,9,4,21,5,15,5,14,12,8,19,9]},
];
export const paidCourses=catalogue.map(c=>({...c,kind:'paid-recorded',accessDays:90,price:300000,currency:'RUB',lessons:c.modules.flatMap(([title,count])=>Array.from({length:count},(_,i)=>count===1?title:`${title} · часть ${i+1}`))}));
export const isPaidCourse=c=>c?.kind==='paid-recorded';

// Upgrade the existing demo without touching any saved free-course progress or notes.
export function ensurePaidDemo(state) {
  if(state.paidVersion===1)return state;
  state.orders=[
    {id:'DEMO-101',userId:'anna',courseId:'dashboards',status:'paid',createdAt:'2026-11-05T07:00:00.000Z',paidAt:'2026-11-05T07:00:00.000Z',transactionId:'demo-tx-101'},
    {id:'DEMO-102',userId:'mikhail',courseId:'dashboards',status:'pending',createdAt:'2026-11-12T06:00:00.000Z'},
    {id:'DEMO-103',userId:'elena',courseId:'market-strategy',status:'paid',createdAt:'2026-08-01T07:00:00.000Z',paidAt:'2026-08-01T07:00:00.000Z',transactionId:'demo-tx-103'},
    {id:'DEMO-104',userId:'olga',courseId:'market-strategy',status:'failed',createdAt:'2026-11-11T07:00:00.000Z'},
    {id:'DEMO-105',userId:'irina',courseId:'market-strategy',status:'review',createdAt:'2026-11-12T06:00:00.000Z',issue:'Сумма подтверждения не совпала с заказом'},
  ].filter(o=>state.people.some(p=>p.id===o.userId)).map(o=>({...o,amount:300000,currency:'RUB',accessDays:90,source:'Tilda · демо'}));
  state.paymentEvents=[];
  for(const o of state.orders.filter(o=>o.status==='paid')){
    if(!state.enrollments.some(e=>e.userId===o.userId&&e.courseId===o.courseId))state.enrollments.push({id:`paid-${o.id}`,userId:o.userId,courseId:o.courseId,orderId:o.id,start:o.paidAt,until:new Date(Date.parse(o.paidAt)+90*86400000).toISOString(),done:o.userId==='anna'?[0,1,2]:[0,1],lastVisit:null,source:'Оплата · демо',accessStatus:'active'});
  }
  state.paidVersion=1;
  return state;
}
