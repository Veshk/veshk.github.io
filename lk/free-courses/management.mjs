import {getCourse,active} from './domain.mjs?v=20261112-courses-6';
import {isPaidCourse} from './paid-catalog.mjs?v=20261112-courses-6';
import {managementNav} from './paid-view.mjs?v=20261112-courses-6';
const escape=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
export function managementHub(state){
  const kinds=[
    ['Программы обучения','Группы, расписание, записи занятий и администраторы','../admin.html','Открыть группы'],
    ['Бесплатные курсы','Уроки и материалы, запись с Тильды, доступ на месяц','#/admin','Управлять курсами'],
    ['Курсы в записи','Уроки и материалы, заказы, оплата и персональные сроки','#/paid','Управлять курсами']
  ];
  return '<div class="wrap management-hub">'+managementNav('manage')+'<div class="page-title"><p class="eyebrow">Панель администратора</p><h1>Управление обучением</h1><p class="muted">Три вида обучения с разными настройками и общей карточкой слушателя</p></div><div class="grid three">'+kinds.map(([title,text,url,button])=>'<article class="card"><h2>'+title+'</h2><p class="muted">'+text+'</p><a class="btn" href="'+url+'">'+button+' →</a></article>').join('')+'</div><section class="card shared-directory"><div><p class="eyebrow">Общее для всех видов обучения</p><h2>Слушатели</h2><p class="muted">Один человек — одна карточка. Здесь вместе видны его программы, курсы, заказы, сроки и прогресс.</p></div><a class="btn secondary" href="#/people">Открыть общий список →</a></section><div class="grid two"><section class="card"><h3>Управление курсом</h3><p class="muted">Откройте нужный раздел, выберите курс и редактируйте его уроки. Изменения содержания относятся к этому курсу.</p></section><section class="card"><h3>Управление человеком</h3><p class="muted">Найдите человека в общем списке. В его карточке выберите конкретное зачисление, чтобы проверить оплату или продлить доступ.</p></section></div></div>';
}
export function peopleTable(state,query='',kind='all'){
  const people=state.people.filter(p=>{
    const enrollments=state.enrollments.filter(e=>e.userId===p.id);
    return (p.name+' '+p.email).toLowerCase().includes(query.toLowerCase())&&(kind==='all'||kind==='program'&&p.paid||enrollments.some(e=>kind==='recorded'?isPaidCourse(getCourse(e.courseId)):kind==='free'&&!isPaidCourse(getCourse(e.courseId))));
  });
  if(!people.length)return '<div class="card empty"><h3>Слушатели не найдены</h3><p>Измените поиск или вид обучения</p></div>';
  return '<div class="table-wrap" tabindex="0" role="region" aria-label="Общий список слушателей"><table><thead><tr><th>Слушатель</th><th>Программы обучения</th><th>Бесплатные курсы</th><th>Курсы в записи</th><th></th></tr></thead><tbody>'+people.map(p=>{
    const es=state.enrollments.filter(e=>e.userId===p.id);
    const list=paid=>es.filter(e=>isPaidCourse(getCourse(e.courseId))===paid).map(e=>'<strong>'+escape(getCourse(e.courseId).short)+'</strong><small>'+(active(state,e)?'Доступ открыт':'Доступ завершён')+'</small>').join('')||'—';
    return '<tr><td><button class="text-button" data-action="paid-person" data-id="'+escape(p.id)+'">'+escape(p.name)+'</button><small>'+escape(p.email)+'</small></td><td>'+(p.paid?'MBA Стратегия<br><small>Учебная группа</small>':'—')+'</td><td>'+list(false)+'</td><td>'+list(true)+'</td><td><button class="btn secondary sm" data-action="paid-person" data-id="'+escape(p.id)+'">Карточка →</button></td></tr>';
  }).join('')+'</tbody></table></div><p class="small muted space">Найдено слушателей: '+people.length+'. Один человек показывается одной строкой.</p>';
}
export function peoplePage(state){
  return '<div class="wrap">'+managementNav('people')+'<div class="page-title"><p class="eyebrow">Единый список</p><h1>Слушатели</h1><p class="muted">Всё обучение человека и его заказы в одной карточке</p></div><div class="filters"><label class="field search">Слушатель<input type="search" id="people-search" placeholder="Имя или email"></label><label class="field">Вид обучения<select id="people-kind"><option value="all">Все виды обучения</option><option value="program">Программа обучения</option><option value="free">Бесплатный курс</option><option value="recorded">Курс в записи</option></select></label></div><div id="people-results">'+peopleTable(state)+'</div></div>';
}
