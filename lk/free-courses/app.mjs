import {recordedPage,setupRecordedPage,refreshRecordedModules} from './recorded-page.mjs?v=20261112-courses-7';
import {managementHub,peoplePage,peopleTable} from './management.mjs?v=20261112-courses-6';
import {ensurePaidDemo,isPaidCourse} from './paid-catalog.mjs?v=20261112-courses-6';
import {applyDemoPayment} from './paid-domain.mjs?v=20261112-courses-6';
import {paidAdmin,paidLogic,orderDialog,paidPerson,unpaidCard,learnerOrder,managementNav,accessLabel} from './paid-view.mjs?v=20261112-courses-6';
import {mountKinescope} from './lesson-player.mjs?v=20261112-courses-6';
import {lessonMedia,courseMaterials,courseAbout} from './course-view.mjs?v=20261112-courses-6';
import {courses,seed,getCourse,active,pending,findEnrollment,percent,shortDate,fullDate,suggestedExtension,register,requestExtension,extend,retryMail,markLesson,completeLesson} from './domain.mjs?v=20261112-courses-6';
import {logicPage} from './logic.mjs?v=20261112-courses-6';
import {intakePage} from './intake.mjs?v=20261112-courses-6';

const KEY='vesh.free-courses.prototype.v1';
let state;
try { const saved=JSON.parse(localStorage.getItem(KEY)); state=saved?.version===1 && Array.isArray(saved.enrollments) ? saved : seed(); } catch { state=seed(); }
ensurePaidDemo(state);
let paidFilters={tab:'orders',query:'',course:'all',status:'all'};
const freeEnrollments=()=>state.enrollments.filter(e=>!isPaidCourse(getCourse(e.courseId)));
let disposeVideo=async()=>{},renderVersion=0;
let adminTab='enrollments', adminQuery='', adminCourse='all', adminStatus='all', learnFilter='all', toastTimer;
const app=document.querySelector('#app'), dialog=document.querySelector('#dialog');
export const h=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const initials=name=>name.split(' ').slice(0,2).map(x=>x[0]).join('').toUpperCase();
const word=(n,one,few,many)=>n%100>=11&&n%100<=14?many:n%10===1?one:n%10>=2&&n%10<=4?few:many;
const user=()=>state.people.find(p=>p.id===state.userId)||state.people[0];
const route=()=>location.hash.slice(1).split('?')[0]||'/overview';
const go=path=>{if(route()===path)render();else location.hash=path;};
const save=()=>{try{localStorage.setItem(KEY,JSON.stringify(state));}catch{toast('Браузер не сохранил изменения. Не закрывайте вкладку.');}};
const toast=message=>{const node=document.querySelector('#toast');node.textContent=message;node.classList.add('visible');clearTimeout(toastTimer);toastTimer=setTimeout(()=>node.classList.remove('visible'),4500);};
const badge=e=>`<span class="chip ${active(state,e)?'green':'gray'}">${accessLabel(state,e)}</span>`;
const progress=e=>`<div class="row between small muted"><span>${e.done.length} из ${getCourse(e.courseId).lessons.length} уроков</span><span>${percent(e)}%</span></div><div class="progress" role="progressbar" aria-label="Отмеченные уроки" aria-valuenow="${percent(e)}" aria-valuemin="0" aria-valuemax="100"><span style="width:${percent(e)}%"></span></div>`;
const courseOptions=(chosen,all=false)=>`${all?'<option value="all">Все курсы</option>':''}${courses.filter(c=>!isPaidCourse(c)).map(c=>`<option value="${c.id}" ${chosen===c.id?'selected':''}>${c.short}</option>`).join('')}`;
const routeLink=(path,label,selected)=>`<a href="#${path}" ${selected?'aria-current="page"':''}>${label}</a>`;

function shell(content,section='overview') {
  const person=user();
  return `<div class="review-bar"><div class="wrap review-inner"><div class="review-label"><b>ПРОТОТИП</b><span>Версия 7 — ${shortDate(state.now)} ${new Date(state.now).getUTCFullYear()}</span></div><nav class="review-nav" aria-label="Разделы прототипа">${routeLink('/overview','Обзор',section==='overview')}${routeLink('/manage','Управление',section==='manage')}${routeLink('/admin','Бесплатные курсы',section==='admin')}${routeLink('/paid','Курсы в записи',section==='paid')}${routeLink('/intake/feedback','Связь с Tilda',section==='intake')}${routeLink('/learn','Кабинет',section==='learn')}${routeLink('/logic','Логика',section==='logic')}</nav></div></div>
  <header class="site-header ${section==='learn'?'portal-header':''}"><div class="wrap site-head"><a class="brand" href="#/overview"><img src="../brand/vesh-logo.svg" alt="ВЭШ"><span>${section==='learn'?'Пространство<br>вашего обучения':'Высшая<br>экономическая школа'}</span></a>${['admin','paid','manage'].includes(section)?'<div class="row"><span class="chip">Администратор</span><a class="btn secondary sm" href="../admin.html">Основные группы ↗</a></div>':section==='intake'?'<div class="row"><a class="btn secondary" href="#/learn">Мой кабинет</a></div>':`<a class="profile-link" href="#/learn"><span class="avatar">${h(initials(person.name))}</span><span class="name">${h(person.name.split(' ')[0])}<small class="muted" style="display:block">Учебный кабинет</small></span></a>`}</div></header>
  <main id="main" class="${section==='learn'?'portal-view':['paid','manage','admin'].includes(section)?'paid-admin':''}" tabindex="-1">${content}</main><footer class="footer"><div class="wrap row between"><p>ВЭШ — Концепт общего кабинета. Все слушатели вымышлены. Действия сохраняются только в этом браузере. Оплата и отправка писем не выполняются.</p><div class="row flex-wrap"><a href="#/logic">Что уже работает в прототипе</a><button class="text-button" data-action="reset">Сбросить демо</button></div></div></footer>`;
}

function overview() {
  return `<div class="wrap"><section class="overview-hero"><div><p class="eyebrow">Новая часть личного кабинета ВЭШ</p><h1>Все виды обучения.<br><em>Один кабинет.</em></h1><p class="lead">Запись — на действующем лендинге Tilda. Уроки, материалы и доступ — в новом общем кабинете ВЭШ.</p><div class="row flex-wrap"><a class="btn" href="#/intake/feedback">Проверить связь с Tilda →</a><a class="btn secondary" href="#/paid">Курсы в записи и оплаты →</a></div><p class="small muted" style="margin:20px 0 0">Интерактивный концепт — 3 бесплатных и 2 платных курса — данные на 12 ноября 2026</p></div><div class="cover-map"><div class="card"><div class="row"><span class="avatar">АП</span><div><strong>Анна Петрова</strong><p class="small muted" style="margin:5px 0 0">Один email. Все программы ВЭШ.</p></div></div></div><div class="map-arrow">↓</div><div class="mini-course"><div><strong>Дашборды в Excel</strong><small>Курс в записи — 90 дней</small></div><span class="chip green">Оплачен</span></div><div class="mini-course"><div><strong>MBA Стратегия</strong><small>Программа обучения</small></div><span class="chip green">Учится</span></div><div class="mini-course"><div><strong>Обратная связь</strong><small>Бесплатный курс — до 4 декабря</small></div><span class="chip green">2 / 8</span></div><div class="mini-course"><div><strong>Power Query</strong><small>Срок закончился — можно продлить</small></div><span class="chip gray">3 / 6</span></div><p class="small muted" style="margin:22px 0 0">Каждый курс — со своим сроком и прогрессом.</p></div></section>
  <section class="section" style="padding-top:12px"><div class="section-head"><div><p class="eyebrow">Что можно посмотреть</p><h2>Четыре связанные части</h2></div><a class="small" href="#/logic">Правила и план подключения →</a></div><div class="grid two">${[
    ['/manage','01','Панель управления','Все курсы, состав каждой группы, сроки доступа, запросы на продление и письма с ошибкой.'],
    ['/intake/feedback','02','Запись на Tilda → новый ЛК','Действующие лендинги и формы остаются на Tilda. Проверяем передачу заявки и автоматическое открытие курса.'],
    ['/learn','03','Моё обучение','Программы обучения, платные и бесплатные курсы рядом. Один человек — один аккаунт.'],
    ['/preview/power-query/0','04','Кабинет курса','Реальные уроки Power Query и «Обратной связи», материалы и сведения об экспертах.'],
  ].map(([url,n,title,text])=>`<a class="card link-card" href="#${url}"><span class="number">${n}</span><div class="row between"><h3>${title}</h3><span class="arrow">↗</span></div><p>${text}</p></a>`).join('')}</div></section>
  <section class="section" style="padding-top:0"><div class="section-head"><h2>Проверить за несколько минут</h2></div><div class="flow"><div><div class="step">ШАГ 01</div><h3>Получите заявку</h3><p>В разделе «Связь с Tilda» смоделируйте поступление тестовой заявки из существующей формы.</p></div><div><div class="step">ШАГ 02</div><h3>Откройте письмо</h3><p>Посмотрите макет письма и войдите в кабинет через демо-подтверждение.</p></div><div><div class="step">ШАГ 03</div><h3>Отметьте урок</h3><p>Откройте курс, сохраните заметку и отметьте урок пройденным.</p></div><div><div class="step">ШАГ 04</div><h3>Проверьте панель</h3><p>Новая запись и прогресс появятся в управлении. Можно продлить доступ.</p></div></div><div class="notice space">У Анны уже есть программа обучения и курс с истёкшим доступом. В кабинете переключитесь на Анну, запросите продление Power Query, затем одобрите его в управлении.</div></section></div>`;
}


function receiptPage(receipt) {
  return `<div class="wrap"><section class="success-panel"><span class="chip green">Тестовое событие обработано</span><h1 style="font-size:38px;margin-top:24px">Заявка из Tilda принята</h1><p class="lead">Новый ЛК получил данные записи. Теперь можно проверить макет письма и появление курса в кабинете.</p><div class="notice">Это экран проверки интеграции. Слушатель по-прежнему записывается на Tilda и получает ответ действующей формы.</div><div class="card space"><p class="eyebrow">Только в прототипе</p><h3>Заглянем в тестовый почтовый ящик</h3><p class="small muted">Настоящее письмо не отправляется. Ниже можно посмотреть содержание и смоделировать подтверждение email.</p><button class="btn" data-action="show-mail" data-id="${h(receipt.id)}">Показать макет письма →</button></div><a class="small" href="#/admin" style="display:inline-block;margin-top:25px">Посмотреть запись в управлении →</a></section></div>`;
}

function mailPage(receipt) {
  const person=state.people.find(p=>p.id===receipt.userId), enrollment=state.enrollments.find(e=>e.id===receipt.enrollmentId), course=getCourse(enrollment.courseId);
  return `<div class="wrap"><section class="success-panel"><p class="eyebrow">Макет письма — ничего не отправлено</p><div class="mail-preview"><div class="mail-meta"><div><span class="muted">Кому:</span> ${h(person.email)}</div><div><span class="muted">Тема:</span> Ваш курс «${course.short}» в ВЭШ</div></div><h2>${active(state,enrollment)?'Курс ждёт вас':'Ваш курс сохранён'}</h2><p>${h(person.name)}, здравствуйте!</p><p>${active(state,enrollment)?`Курс «${course.short}» доступен до <strong>${fullDate(enrollment.until)}</strong> по Екатеринбургу.`:`Срок доступа к курсу «${course.short}» закончился. Прогресс сохранён. В кабинете можно отправить запрос на продление.`}</p><p>${receipt.isNew?'Ваш логин — email, указанный при записи. Предлагаем при первом входе подтверждать почту одноразовой ссылкой и задавать пароль.':'Войдите в существующий аккаунт. Ваш пароль и доступ к другим программам не меняются.'}</p>${receipt.duplicate?'<div class="notice">Вы уже записаны на этот курс. Повторная заявка сохранила прежний срок доступа.</div>':''}<button class="btn full space" data-action="confirm-email" data-id="${h(receipt.id)}">${receipt.isNew?'Смоделировать подтверждение и вход':'Смоделировать вход в свой аккаунт'}</button><p class="small muted" style="margin:18px 0 0">В рабочем ЛК вход будет через подтверждённый аккаунт. Эта кнопка — переход между экранами демо.</p></div></section></div>`;
}

function adminPage() {
  const unique=new Set(freeEnrollments().map(e=>e.userId)).size, requests=state.requests.filter(r=>r.status==='pending'&&freeEnrollments().some(e=>e.id===r.enrollmentId)).length, errors=state.mail.filter(m=>m.status==='error'&&freeEnrollments().some(e=>e.id===m.enrollmentId)).length;
  return `<div class="wrap">${managementNav(false)}<div class="breadcrumb"><a href="../admin.html">Управление</a><span>›</span><span>Бесплатные курсы</span></div><div class="page-title row between flex-wrap"><div><p class="eyebrow">Отдельный раздел управления</p><h1>Бесплатные курсы</h1><p class="muted">Слушатели, доступ и прогресс — по каждому курсу.</p></div><a class="btn secondary" href="#/intake/feedback">Связь с Tilda →</a></div><div class="stats"><div class="stat"><span class="label">Курсов в прототипе</span><span class="value">3 <small>из 24 найденных</small></span></div><div class="stat"><span class="label">Слушателей</span><span class="value">${unique}</span></div><div class="stat"><span class="label">Открытых доступов</span><span class="value">${freeEnrollments().filter(e=>active(state,e)).length} <small>из ${freeEnrollments().length}</small></span></div><div class="stat"><span class="label">Ждут продления</span><span class="value">${requests}</span></div></div>${requests||errors?`<div class="alert-list">${requests?`<button data-action="filter-requests">${requests} запрос(а) на продление →</button>`:''}${errors?`<button class="error" data-action="filter-errors">Письмо с ошибкой: ${errors} →</button>`:''}</div>`:''}<div class="tabs" role="tablist" aria-label="Управление курсами">${[['enrollments','Зачисления'],['courses','Курсы и группы'],['history','История действий']].map(([id,label])=>`<button role="tab" aria-selected="${adminTab===id}" data-action="admin-tab" data-id="${id}">${label}</button>`).join('')}</div>${adminTab==='enrollments'?`<div class="filters"><label class="field search">Найти слушателя<input id="admin-search" type="search" placeholder="Имя или email" value="${h(adminQuery)}"></label><label class="field">Курс / группа<select id="admin-course">${courseOptions(adminCourse,true)}</select></label><label class="field">Показать<select id="admin-status">${[['all','Все зачисления'],['active','Доступ открыт'],['expired','Срок истёк'],['request','Ждут продления'],['not-started','Не открывали курс'],['mail-error','Ошибка письма']].map(([value,title])=>`<option value="${value}" ${adminStatus===value?'selected':''}>${title}</option>`).join('')}</select></label></div><div id="enrollment-results">${adminTable()}</div>`:adminTab==='courses'?adminCourses():adminHistory()}<div class="notice space">Группа бесплатного курса — постоянный список его слушателей. У каждого своя дата окончания доступа. Основные учебные группы сохраняются в отдельном разделе.</div></div>`;
}
function adminTable() {
  const entries=freeEnrollments().filter(e=>{
    const person=state.people.find(p=>p.id===e.userId);
    const matches=(person.name+' '+person.email).toLowerCase().includes(adminQuery.trim().toLowerCase());
    const statuses={all:true,active:active(state,e),expired:!active(state,e),request:Boolean(pending(state,e.id)),'not-started':!e.lastVisit,'mail-error':state.mail.some(m=>m.enrollmentId===e.id&&m.status==='error')};
    return matches&&(adminCourse==='all'||e.courseId===adminCourse)&&statuses[adminStatus];
  }).sort((a,b)=>Date.parse(b.start)-Date.parse(a.start));
  if(!entries.length)return '<div class="card empty"><h3>Таких зачислений пока нет</h3><p>Измените поиск или фильтры.</p><button class="btn secondary" data-action="clear-filters">Сбросить фильтры</button></div>';
  return `<div class="table-wrap" role="region" aria-label="Список зачислений, на узком экране прокручивается по горизонтали" tabindex="0"><table><thead><tr><th>Слушатель</th><th>Курс / группа</th><th>Доступ</th><th>Прогресс</th><th>Письмо — демо</th><th><span class="small">Действия</span></th></tr></thead><tbody>${entries.map(e=>{const p=state.people.find(p=>p.id===e.userId),c=getCourse(e.courseId),mail=state.mail.filter(m=>m.enrollmentId===e.id&&m.status==='error').at(-1)||state.mail.filter(m=>m.enrollmentId===e.id).at(-1);return `<tr><td><div class="table-person"><span class="avatar">${h(initials(p.name))}</span><div><strong>${h(p.name)}</strong><small>${h(p.email)}</small>${p.paid?'<small style="color:var(--blue);margin-top:4px">Также программа обучения</small>':''}</div></div></td><td><strong>${c.short}</strong><small>С ${shortDate(e.start)}</small></td><td>${badge(e)}<small style="margin-top:6px" title="${fullDate(e.until)}">До ${shortDate(e.until)}</small>${pending(state,e.id)?'<small style="color:#8d6016;margin-top:4px">Запрос на продление</small>':''}</td><td><span>${e.done.length} / ${c.lessons.length}</span><div class="progress mini"><span style="width:${percent(e)}%"></span></div><small>${e.lastVisit?'Вход '+shortDate(e.lastVisit):'Не открывал(а)'}</small></td><td>${mail?.status==='error'?`<span class="chip red">Ошибка</span><button class="text-button" data-action="retry-mail" data-id="${h(mail.id)}" style="display:block;margin-top:5px">Повторить</button>`:mail?'<span class="chip green">Подготовлено</span>':'<span class="muted">Нет событий</span>'}</td><td><button class="btn secondary sm" data-action="person" data-id="${h(e.id)}">Открыть →</button></td></tr>`;}).join('')}</tbody></table></div><p class="table-foot">Показано ${entries.length} из ${freeEnrollments().length} зачислений — слушателей в выборке: ${new Set(entries.map(e=>e.userId)).size} — время Екатеринбурга</p>`;
}
function adminCourses() {
  return `<div class="grid three">${courses.filter(c=>!isPaidCourse(c)).map(c=>{const entries=state.enrollments.filter(e=>e.courseId===c.id);return `<article class="card admin-course-card ${c.color}"><span class="eyebrow">${c.category}</span><h3>${c.short}</h3><p class="small muted">${c.lessons.length} ${word(c.lessons.length,'урок','урока','уроков')} — календарный месяц доступа</p><div class="metrics"><div><b>${entries.length}</b><small>слушателей</small></div><div><b>${entries.filter(e=>active(state,e)).length}</b><small>активных</small></div></div><div class="card-actions">${c.content?`<a class="btn secondary sm" href="#/preview/${c.id}/0">Просмотреть ЛК →</a>`:""}<button class="btn sm" data-action="course-group" data-id="${c.id}">Открыть группу →</button><a class="btn secondary sm" href="${c.source}" target="_blank" rel="noopener">Лендинг Tilda ↗</a></div></article>`;}).join('')}</div><div class="card space"><h3>Содержание курса — в новом ЛК</h3><p class="muted small">Уроки и материалы перенесены в прототип двух кабинетов; редактор курса — следующий этап. Продающая страница остаётся в Tilda и редактируется там же; в карточке курса храним её ссылку и связь с формой. Текущие 24 курса инвентаризированы; в прототип включены три примера.</p><a class="small" href="#/logic">Посмотреть план переноса →</a></div>`;
}
function adminHistory() {
  return `<div class="card"><h3>Журнал операций в этом браузере</h3>${state.audit.length?`<ol class="timeline">${[...state.audit].reverse().map(a=>{const e=state.enrollments.find(e=>e.id===a.enrollmentId),p=state.people.find(p=>p.id===e?.userId);return `<li><small>${fullDate(a.at)} — ${h(p?.name)} — ${getCourse(e?.courseId)?.short||''}</small>${h(a.text)}</li>`;}).join('')}</ol>`:'<div class="empty"><p>Здесь появятся зачисления, продления и повторы писем, которые вы проверите в прототипе.</p></div>'}</div>`;
}

function learning() {
  const person=user(), entries=state.enrollments.filter(e=>e.userId===person.id);
  const visible=entries.filter(e=>learnFilter==='all'||learnFilter==='free'&&!isPaidCourse(getCourse(e.courseId))||learnFilter==='recorded'&&isPaidCourse(getCourse(e.courseId))||learnFilter==='expired'&&!active(state,e));
  const awaiting=(learnFilter==='all'||learnFilter==='recorded')?state.orders.filter(o=>o.userId===person.id&&!findEnrollment(state,person.id,o.courseId)):[];
  const showPaid=person.paid&&(learnFilter==='all'||learnFilter==='program');
  return `<div class="wrap"><div class="page-title"><p class="eyebrow">Ваш личный кабинет</p><h1>Моё обучение</h1><p class="muted">${h(person.name.split(' ')[0])}, здесь ваши программы и курсы ВЭШ.</p></div><div class="identity-bar"><span><strong>Демо-сценарий.</strong> Выберите слушателя для проверки общего кабинета.</span><select id="demo-user" aria-label="Тестовый слушатель">${state.people.map(p=>`<option value="${h(p.id)}" ${p.id===person.id?'selected':''}>${h(p.name)}${p.paid?' — есть программа обучения':''}</option>`).join('')}</select></div><div class="tabs" role="tablist" aria-label="Виды обучения">${[['all','Всё обучение'],['program','Программы обучения'],['free','Бесплатные курсы'],['recorded','Курсы в записи'],['expired','Завершён доступ']].map(([id,label])=>`<button role="tab" aria-selected="${learnFilter===id}" data-action="learn-filter" data-id="${id}">${label}</button>`).join('')}</div><div class="grid three">${showPaid?`<article class="card learning-card"><div class="course-strip navy"><span class="chip">Программа обучения</span><h3>MBA Стратегия</h3></div><div class="body"><p>Учебная группа — пример программы</p><div class="row between"><span class="chip green">Обучение продолжается</span></div><div class="bottom"><small class="muted">Доступ по срокам вашей учебной группы</small><a class="btn full" href="#/program">Открыть программу →</a></div></div></article>`:''}${visible.map(e=>learningCard(e)).join('')}${awaiting.map(o=>unpaidCard(state,o)).join('')}</div>${!showPaid&&!visible.length&&!awaiting.length?'<div class="card empty"><h3>Здесь пока нет курсов</h3><p>Выберите другой раздел или запишитесь на бесплатный курс.</p><a class="btn" href="https://lk.veshk.ru/demo" target="_blank" rel="noopener">Выбрать курс на Tilda ↗</a></div>':''}<section class="section"><div class="section-head"><div><h2>Можно изучить ещё</h2><p class="muted small">Бесплатные курсы в записи — в удобном для вас темпе.</p></div></div><div class="grid three">${courses.filter(c=>!isPaidCourse(c)).map(c=>`<a class="card link-card ${c.color}" href="${c.source}" target="_blank" rel="noopener"><p class="eyebrow">${c.category}</p><h3>${c.short}</h3><div class="row between small"><span>${c.lessons.length} ${word(c.lessons.length,'урок','урока','уроков')} — 1 месяц</span><span class="arrow">→</span></div></a>`).join('')}</div></section></div>`;
}
function learningCard(e) {
  const c=getCourse(e.courseId),isActive=active(state,e),request=pending(state,e.id),next=Math.min(c.lessons.length-1,c.lessons.findIndex((_,i)=>!e.done.includes(i))<0?0:c.lessons.findIndex((_,i)=>!e.done.includes(i)));
  return `<article class="card learning-card ${isActive?'':'expired'}"><div class="course-strip ${c.color}"><span class="chip">${isPaidCourse(c)?'Курс в записи':'Бесплатный курс'}</span><h3>${c.title}</h3></div><div class="body"><p>${c.expert}</p>${progress(e)}<div class="bottom"><small class="${isActive?'muted':''}">${isActive?'Доступ до':e.accessStatus==='revoked'?'Доступ закрыт — прежний срок до':'Доступ закончился'} ${shortDate(e.until)}${isActive?' — '+Math.ceil((Date.parse(e.until)-Date.parse(state.now))/86400000)+' дн.':''}</small>${isActive?`<a class="btn full" href="#/course/${c.id}/${next}">${e.done.length?'Продолжить':'Начать курс'} →</a>`:e.accessStatus==='revoked'?'<span class="chip gray">Возврат подтверждён</span>':request?'<span class="chip amber">Запрос на продление отправлен</span>':`<button class="btn secondary full" data-action="request-extension" data-id="${h(e.id)}">Попросить продление</button>`}${!isActive?`<a class="small" style="display:block;margin-top:12px" href="#/course/${c.id}/0">Посмотреть курс →</a>`:''}</div></div></article>`;
}

function paidLessonMenu(c,index,base,e){let offset=0;return c.modules.map(([title,count])=>{const start=offset;offset+=count;return `<details class="course-module" ${index>=start&&index<offset?'open':''}><summary>${h(title)} — ${count} ${word(count,'урок','урока','уроков')}</summary><ol class="course-menu">${Array.from({length:count},(_,i)=>{const n=start+i;return `<li><a href="${base}/${n}" ${n===index?'aria-current="page"':''}><span class="lesson-num" data-lesson-num="${n}">${e.done.includes(n)?'✓':String(n+1).padStart(2,'0')}</span><span>Урок ${n+1}<small class="lesson-duration">${c.minutes[n]} мин</small></span></a></li>`;}).join('')}</ol></details>`;}).join('');}

function coursePage(c,index=0,preview=false) {
  const person=user(),e=preview?{id:'preview',courseId:c.id,done:[]}:findEnrollment(state,person.id,c.id);
  if(!e&&isPaidCourse(c)){const order=state.orders.find(o=>o.userId===person.id&&o.courseId===c.id);if(order)return learnerOrder(state,order);}
  if(!e)return `<div class="wrap section"><div class="locked"><div class="symbol">＋</div><h1>Добавьте курс в своё обучение</h1><p class="muted">У ${h(person.name)} пока нет записи на курс «${c.short}».</p><a class="btn" href="${c.source}" target="_blank" rel="noopener">Записаться на Tilda ↗</a><a class="btn secondary" href="#/learn">Моё обучение</a></div></div>`;
  index=Number.isInteger(index)&&index>=0&&index<c.lessons.length?index:0;
  const isActive=preview||active(state,e),request=preview?null:pending(state,e.id),noteKey=`${e.id}:${index}`,base=`#/${preview?'preview':'course'}/${c.id}`;
  if(isActive&&!preview){e.lastVisit=state.now;save();}
  if(isPaidCourse(c)&&isActive)return recordedPage({course:c,index,enrollment:e,preview,note:state.notes[noteKey]||'',progress:progress(e),until:preview?null:fullDate(e.until)});
  const notes=preview?'':`<section class="card practice"><h3>Мои заметки</h3><form id="note-form" data-key="${h(noteKey)}" data-enrollment="${h(e.id)}"><label class="field">Что хочу применить в работе<textarea name="note" maxlength="5000" placeholder="Главная мысль и следующий шаг…">${h(state.notes[noteKey]||'')}</textarea></label><div class="row between flex-wrap" style="margin-top:15px"><button class="btn secondary sm" type="submit">Сохранить заметку</button><button class="text-button" type="button" data-action="download-template">Шаблон заметок (.txt) ↓</button></div></form></section>`;
  const lesson=isActive?`<div class="lesson-heading"><span class="eyebrow">Урок ${index+1} из ${c.lessons.length}${c.content?' — '+c.content.lessons[index].minutes+' мин':''}</span><h1>${h(c.lessons[index])}</h1><p class="small muted">${h(c.expert)} — Курс для самостоятельного изучения</p></div>${lessonMedia(c,index)}<div class="lesson-actions">${preview?'':`<button class="btn ${e.done.includes(index)?'secondary':''}" data-action="mark-lesson" data-id="${h(e.id)}" data-index="${index}">${e.done.includes(index)?'✓ Урок пройден — отменить':'Отметить урок пройденным'}</button>`}${index<c.lessons.length-1?`<a class="btn secondary" href="${base}/${index+1}">Следующий урок →</a>`:`<a class="btn secondary" href="#/${preview?(isPaidCourse(c)?'paid':'admin'):'learn'}">${preview?'К управлению':'К моему обучению'} →</a>`}</div>${preview?'':`<p class="small muted" data-progress-hint style="margin-top:13px">${isPaidCourse(c)?'В рабочем кабинете урок отметится после начала видео. Здесь можно проверить ручную отметку.':c.content?.lessons[index]?.provider==='Kinescope'?'Урок отметится пройденным, когда начнётся видео. Можно также поставить или снять отметку вручную.':'После просмотра видео отметьте урок пройденным.'}</p>`}${courseMaterials(c)}${notes}${courseAbout(c)}`:'';
  return `<div class="wrap"><div class="breadcrumb"><a href="#/${preview?(isPaidCourse(c)?'paid':'admin'):'learn'}">${preview?'Управление курсами':'Моё обучение'}</a><span>›</span><span>${c.short}</span></div>${preview?('<div class="notice preview-notice"><strong>Предпросмотр кабинета курса</strong><span>Проверка содержания для администратора. Доступы, прогресс и заметки слушателей не меняются.</span><a href="#/admin">Вернуться к управлению →</a></div>').replace('#/admin',isPaidCourse(c)?'#/paid':'#/admin'):''}<button class="btn secondary full mobile-course-menu" data-action="toggle-lessons" aria-expanded="false">Программа курса — ${c.lessons.length} ${word(c.lessons.length,'урок','урока','уроков')} ↓</button><div class="course-layout"><aside class="course-sidebar"><div class="sidebar-head"><span class="eyebrow">${isPaidCourse(c)?'Курс в записи':'Бесплатный курс'}</span><h3>${c.short}</h3>${preview?`<small class="muted">${c.lessons.length} уроков${c.content?' — '+c.content.lessons.reduce((sum,l)=>sum+l.minutes,0)+' мин':''}</small>`:`<div id="course-progress">${progress(e)}</div><small class="muted">Доступ до ${fullDate(e.until)}</small>`}</div>${isPaidCourse(c)?paidLessonMenu(c,index,base,e):`<ol class="course-menu">${c.lessons.map((title,i)=>`<li><a href="${base}/${i}" ${i===index?'aria-current="page"':''}><span class="lesson-num" data-lesson-num="${i}">${e.done.includes(i)?'✓':String(i+1).padStart(2,'0')}</span><span>${h(title)}${c.content?`<small class="lesson-duration">${c.content.lessons[i].minutes} мин</small>`:''}</span></a></li>`).join('')}</ol>`}</aside><section class="lesson-body">${isActive?lesson:`<div class="locked"><div class="symbol">◷</div><span class="chip gray">${accessLabel(state,e)}</span><h1 style="margin-top:22px">${e.accessStatus==='revoked'?'Доступ закрыт после возврата':'Продолжим, когда будете готовы'}</h1><p class="muted">${e.accessStatus==='revoked'?'Доступ закрыт после подтверждения возврата.':'Доступ к курсу закончился '+fullDate(e.until)+'.'} Вы прошли ${e.done.length} из ${c.lessons.length} уроков. Прогресс и заметки сохранены.</p>${e.accessStatus==='revoked'?'<p>Возврат по заказу подтверждён. Остальное обучение остаётся доступным по своим условиям.</p>':request?'<div class="notice warm">Запрос на продление отправлен. Решение появится в кабинете.</div>':`<button class="btn" data-action="request-extension" data-id="${h(e.id)}">Попросить продление</button>`}<p class="small muted" style="margin-top:24px">Доступ к остальным вашим курсам и основной программе определяется их собственными сроками.</p><a class="btn secondary" href="#/learn">Моё обучение →</a></div>`}</section></div></div>`;
}

function programPage() {
  if(!user().paid)return '<div class="wrap section"><div class="card empty"><h2>Основной программы пока нет</h2><a class="btn" href="#/learn">Вернуться к обучению</a></div></div>';
  return `<div class="wrap section"><div class="program-preview"><span class="chip">Программа обучения — пример</span><h1 style="margin-top:25px">MBA Стратегия</h1><p>Добро пожаловать, ${h(user().name.split(' ')[0])}. Ваше обучение продолжается.</p><div class="row flex-wrap"><span class="chip">Расписание</span><span class="chip">Записи занятий</span><span class="chip">Материалы</span></div></div><div class="notice space">Окончание или продление бесплатного курса не меняет доступ к этой программе. В рабочем ЛК здесь открывается существующая страница учебной группы.</div><div class="row space flex-wrap"><a class="btn" href="../index.html">Открыть действующий прототип ЛК ↗</a><a class="btn secondary" href="#/learn">Моё обучение</a></div></div>`;
}

async function render() {
  const version=++renderVersion;
  await disposeVideo();
  if(version!==renderVersion)return;
  const path=route(),parts=path.split('/');let content,section;
  if(path==='/manage'){content=managementHub(state);section='manage';}
  else if(path==='/people'){content=peoplePage(state);section='manage';}
  else if(path==='/paid'){content=paidAdmin(state,paidFilters);section='paid';}
  else if(path==='/paid-logic'){content=paidLogic();section='paid';}
  else if(parts[1]==='order'){const order=state.orders.find(o=>o.id===parts[2]&&o.userId===state.userId);content=order?learnerOrder(state,order):learning();section='learn';}
  else if(path==='/admin'){content=adminPage();section='admin';}
  else if(['intake','landing'].includes(parts[1])){const c=getCourse(parts[2]);content=isPaidCourse(c)?paidLogic():intakePage(c||courses[0],courseOptions,word);section=isPaidCourse(c)?'paid':'intake';}
  else if(path==='/learn'){content=learning();section='learn';}
  else if(parts[1]==='preview' && (getCourse(parts[2])?.content||isPaidCourse(getCourse(parts[2])))){content=coursePage(getCourse(parts[2]),Number(parts[3]||0),true);section='learn';}
  else if(parts[1]==='course'){content=coursePage(getCourse(parts[2])||courses[0],Number(parts[3]||0));section='learn';}
  else if(path==='/program'){content=programPage();section='learn';}
  else if(path==='/logic'){content=logicPage(state,h);section='logic';}
  else if(['receipt','mail'].includes(parts[1])){const receipt=state.submissions.find(s=>s.id===parts[2]);content=receipt?(parts[1]==='receipt'?receiptPage(receipt):mailPage(receipt)):overview();section='intake';}
  else{content=overview();section='overview';}
  app.innerHTML=shell(content,section);
  setupRecordedPage();
  connectLessonPlayer(path);
  document.title=`${{overview:'Общий кабинет',manage:'Управление обучением',paid:'Курсы в записи',admin:'Управление курсами',intake:'Запись на Tilda — новый ЛК',learn:'Моё обучение',logic:'Логика и подключение'}[section]} — концепт ВЭШ`;
}

function refreshLessonProgress(enrollmentId,index) {
  const e=state.enrollments.find(item=>item.id===enrollmentId);
  const button=app.querySelector('[data-action="mark-lesson"]'),done=e.done.includes(index);
  if(button){button.classList.toggle('secondary',done);button.textContent=done?'✓ Урок пройден — отменить':'Отметить урок пройденным';}
  const meter=app.querySelector('#course-progress'),number=app.querySelector(`[data-lesson-num="${index}"]`);
  if(meter)meter.innerHTML=progress(e);
  if(number)number.textContent=done?'✓':String(index+1).padStart(2,'0');
  refreshRecordedModules(e.done);
}

function connectLessonPlayer(path) {
  const iframe=app.querySelector('[data-kinescope-url]');
  if(!iframe){disposeVideo=async()=>{};return;}
  const button=app.querySelector('[data-action="mark-lesson"]');
  const personId=state.userId,enrollmentId=button?.dataset.id,index=Number(button?.dataset.index);
  disposeVideo=mountKinescope(iframe,()=>{
    // The same video can be open in an admin preview or another person's demo.
    if(!button||route()!==path||state.userId!==personId)return;
    try{
      if(completeLesson(state,enrollmentId,index)){
        save();refreshLessonProgress(enrollmentId,index);
        toast('Видео началось — урок отмечен пройденным.');
      }
    }catch{ /* Access was revoked or expired before the playback event. */ }
  },{onUnavailable:()=>{
    const hint=app.querySelector('[data-progress-hint]');
    if(hint)hint.textContent='Автоматическая отметка сейчас недоступна. После просмотра отметьте урок вручную.';
  }});
}

function openDialog(html) {dialog.innerHTML=html;if(!dialog.open)dialog.showModal();}
const dialogHead=title=>`<div class="dialog-head"><h2 id="dialog-title">${title}</h2><button class="dialog-close" data-action="close-dialog" aria-label="Закрыть">×</button></div>`;

const showPaidOrder=id=>{const o=state.orders.find(o=>o.id===id);if(o)openDialog(dialogHead('Заказ '+h(o.id))+orderDialog(state,o));};
function personDialog(enrollmentId) {
  const e=state.enrollments.find(e=>e.id===enrollmentId),p=state.people.find(p=>p.id===e.userId),c=getCourse(e.courseId),request=pending(state,e.id);
  openDialog(`${dialogHead(h(p.name))}<p class="small muted">${h(p.email)} — ${h(p.job)}</p><div class="summary-dl"><div><dt>Выбранный курс</dt><dd>${c.short}</dd></div><div><dt>Дата зачисления</dt><dd>${fullDate(e.start)}</dd></div><div><dt>Доступ до</dt><dd>${fullDate(e.until)}</dd></div><div><dt>Прогресс</dt><dd>${e.done.length} / ${c.lessons.length} — ${percent(e)}%</dd></div></div>${request?`<div class="notice warm"><strong>Запрос на продление</strong><p>${h(request.reason)}</p><small>${fullDate(request.at)}</small></div>`:''}${isPaidCourse(c)?'<div class="notice warm">Платное продление оформляется отдельным заказом после согласования условий. Здесь можно оформить административное исключение с обязательной причиной; это не подтверждение оплаты.</div>':''}<form id="extend-form" data-id="${h(e.id)}" class="dialog-section"><h3>${request?'Одобрить продление':'Продлить доступ к этому курсу'}</h3><div class="grid two"><label class="field">Новая дата окончания<input name="until" type="date" value="${suggestedExtension(state,e)}" min="${new Date(Math.max(Date.parse(state.now),Date.parse(e.until))+5*3600000+1).toISOString().slice(0,10)}" required></label><label class="field">Причина<input name="reason" placeholder="Например: не успел(а) пройти" required maxlength="300" ${request?`value="По запросу слушателя"`:''}></label></div><p class="small muted" style="margin:12px 0">До конца выбранного дня по Екатеринбургу. Доступ к другим программам не изменится.</p><p class="form-error" role="alert"></p><button class="btn" type="submit">${request?'Одобрить и продлить':'Продлить доступ'}</button></form><div class="dialog-section"><h3>Всё обучение этого слушателя</h3>${p.paid?'<div class="enrollment-tile"><strong>MBA Стратегия</strong><span class="chip green">Программа обучения — учится</span></div>':''}${state.enrollments.filter(item=>item.userId===p.id).map(item=>`<div class="enrollment-tile"><div class="row"><div><strong>${getCourse(item.courseId).short}</strong><span class="muted">До ${fullDate(item.until)}</span></div>${badge(item)}</div></div>`).join('')}</div>${state.audit.some(a=>a.enrollmentId===e.id)?`<div class="dialog-section"><h3>История выбранного курса</h3><ol class="timeline">${state.audit.filter(a=>a.enrollmentId===e.id).reverse().map(a=>`<li><small>${fullDate(a.at)}</small>${h(a.text)}</li>`).join('')}</ol></div>`:''}`);
}

document.addEventListener('click',event=>{
  const button=event.target.closest('[data-action]');if(!button)return;
  const action=button.dataset.action,id=button.dataset.id;
  if(action==='paid-tab'){paidFilters.tab=id;render();return;}
  if(action==='paid-order'){showPaidOrder(id);return;}
  if(action==='paid-person'){const p=state.people.find(p=>p.id===id);if(p)openDialog(dialogHead(h(p.name))+paidPerson(state,p));return;}
  if(action==='paid-enter'){state.userId=id;learnFilter='all';dialog.close();save();go('/learn');return;}
  if(action==='paid-event'){
    const o=state.orders.find(o=>o.id===id),mode=button.dataset.event;
    try{const result=applyDemoPayment(state,{id:mode==='paid'?'demo-paid-'+id:crypto.randomUUID(),orderId:id,status:mode==='mismatch'?'paid':mode,verified:true,courseId:o.courseId,currency:o.currency,amount:mode==='mismatch'?10000:o.amount,transactionId:o.transactionId||'demo-tx-'+id,paidAt:o.paidAt||state.now});save();render();showPaidOrder(id);toast(result.duplicate?'Повтор распознан: срок и прогресс не изменены.':result.review?'Нужна сверка. Доступ не выдан.':'Демо-событие обработано.');}catch(error){toast(error.message);}return;
  }
  if(action==='close-dialog'){dialog.close();return;}
  if(action==='fill-new'||action==='fill-anna'){const form=document.querySelector('#signup-form');form.elements.name.value=action==='fill-anna'?'Анна Петрова':'Новый слушатель';form.elements.email.value=action==='fill-anna'?'anna@example.test':'new@example.test';form.elements.job.value='Руководитель команды';return;}
  if(action==='show-mail'){go(`/mail/${id}`);return;}
  if(action==='confirm-email'){const receipt=state.submissions.find(r=>r.id===id);if(receipt){state.userId=receipt.userId;learnFilter='all';save();go('/learn');toast('Демо: вход подтверждён. Курс уже в кабинете.');}return;}
  if(action==='admin-tab'){adminTab=id;render();return;}
  if(action==='learn-filter'){learnFilter=id;render();return;}
  if(action==='clear-filters'){adminQuery='';adminCourse='all';adminStatus='all';render();return;}
  if(action==='filter-requests'||action==='filter-errors'){adminTab='enrollments';adminStatus=action==='filter-requests'?'request':'mail-error';adminCourse='all';adminQuery='';render();return;}
  if(action==='course-group'){adminTab='enrollments';adminCourse=id;adminStatus='all';adminQuery='';render();return;}
  if(action==='person'){personDialog(id);return;}
  if(action==='retry-mail'){retryMail(state,id);save();render();toast('Повтор письма подготовлен в демо. Срок доступа сохранён.');return;}
  if(action==='request-extension'){openDialog(`${dialogHead('Попросить продление')}<p class="small muted">${isPaidCourse(getCourse(state.enrollments.find(e=>e.id===id)?.courseId))?'Продление платного курса требует согласования условий и оплаты. Сам запрос доступ не открывает. ':''}Напишите, почему нужно больше времени. Администратор рассмотрит запрос. До его решения срок доступа не изменится.</p><form id="request-form" data-id="${h(id)}"><label class="field">Ваш комментарий<textarea name="reason" required maxlength="500" placeholder="Хочу закончить оставшиеся уроки"></textarea></label><p class="form-error" role="alert"></p><div class="dialog-actions"><button type="button" class="btn secondary" data-action="close-dialog">Отмена</button><button class="btn" type="submit">Отправить запрос</button></div></form>`);return;}
  if(action==='mark-lesson'){try{markLesson(state,id,Number(button.dataset.index));save();refreshLessonProgress(id,Number(button.dataset.index));toast('Прогресс сохранён в демо.');}catch(error){toast(error.message);}return;}
  if(action==='toggle-lessons'){const sidebar=document.querySelector('.course-sidebar');sidebar.classList.toggle('open');button.setAttribute('aria-expanded',String(sidebar.classList.contains('open')));return;}
  if(action==='download-template'){const blob=new Blob(['ВЭШ — заметки к уроку\n\nГлавная мысль:\n\nЧто применю в работе:\n\nПервый шаг и срок:\n\nПример шаблона для прототипа.'],{type:'text/plain;charset=utf-8'});const link=document.createElement('a');link.href=URL.createObjectURL(blob);link.download='vesh-lesson-notes.txt';link.click();setTimeout(()=>URL.revokeObjectURL(link.href),1000);return;}
  if(action==='jump-section'){event.preventDefault();document.getElementById(button.dataset.target)?.scrollIntoView({behavior:'smooth'});if(button.dataset.target==='main')document.getElementById('main').focus({preventScroll:true});return;}
  if(action==='advance-clock'){state.now=new Date(Date.parse(state.now)+35*86400000).toISOString();save();render();toast('Демо-время перемещено на 35 дней. Проверьте кабинет.');return;}
  if(action==='reset'){openDialog(`${dialogHead('Начать демонстрацию заново?')}<p>Тестовые заявки, заметки и продления в этом браузере будут удалены. Исходные примеры восстановятся.</p><div class="dialog-actions"><button class="btn secondary" data-action="close-dialog">Отмена</button><button class="btn" data-action="confirm-reset">Сбросить демо</button></div>`);return;}
  if(action==='confirm-reset'){state=ensurePaidDemo(seed());paidFilters={tab:'orders',query:'',course:'all',status:'all'};adminQuery='';adminCourse='all';adminStatus='all';learnFilter='all';dialog.close();save();go('/overview');toast('Исходные тестовые данные восстановлены.');}
});
document.addEventListener('change',event=>{
  if(event.target.id==='people-kind'){document.querySelector('#people-results').innerHTML=peopleTable(state,document.querySelector('#people-search').value,event.target.value);return;}
  if(event.target.id==='paid-course'||event.target.id==='paid-status'){paidFilters[event.target.id==='paid-course'?'course':'status']=event.target.value;render();return;}
  if(event.target.id==='variant')go(`/intake/${event.target.value}`);
  if(event.target.id==='demo-user'){state.userId=event.target.value;save();render();}
  if(event.target.id==='admin-course'){adminCourse=event.target.value;document.querySelector('#enrollment-results').innerHTML=adminTable();}
  if(event.target.id==='admin-status'){adminStatus=event.target.value;document.querySelector('#enrollment-results').innerHTML=adminTable();}
});
document.addEventListener('input',event=>{if(event.target.id==='people-search'){document.querySelector('#people-results').innerHTML=peopleTable(state,event.target.value,document.querySelector('#people-kind').value);return;}if(event.target.id==='paid-search'){paidFilters.query=event.target.value;const parsed=document.createElement('div');parsed.innerHTML=paidAdmin(state,paidFilters);document.querySelector('#paid-results').innerHTML=parsed.querySelector('#paid-results').innerHTML;}if(event.target.id==='admin-search'){adminQuery=event.target.value;document.querySelector('#enrollment-results').innerHTML=adminTable();}});
document.addEventListener('submit',event=>{
  const form=event.target;if(!['signup-form','extend-form','request-form','note-form'].includes(form.id))return;
  event.preventDefault();const data=new FormData(form);try{
    if(form.id==='signup-form'){const receipt=register(state,{name:data.get('name'),email:data.get('email'),job:data.get('job'),courseId:form.dataset.course,consent:data.has('consent'),marketing:data.has('marketing')});save();go(`/receipt/${receipt.id}`);}
    if(form.id==='extend-form'){extend(state,form.dataset.id,data.get('until'),data.get('reason'));save();dialog.close();render();toast('Доступ к выбранному курсу продлён. Изменение записано в историю.');}
    if(form.id==='request-form'){requestExtension(state,form.dataset.id,data.get('reason'));save();dialog.close();render();toast('Запрос передан администратору в демо.');}
    if(form.id==='note-form'){const enrollment=state.enrollments.find(e=>e.id===form.dataset.enrollment&&e.userId===state.userId);if(!active(state,enrollment))throw new Error('Срок доступа закончился.');state.notes[form.dataset.key]=String(data.get('note')).slice(0,5000);save();toast('Заметка сохранена в этом браузере.');}
  }catch(error){const errorNode=form.querySelector('.form-error');if(errorNode)errorNode.textContent=error.message;else toast(error.message);}
});
window.addEventListener('hashchange',async event=>{if(dialog.open)dialog.close();const oldPath=new URL(event.oldURL).hash.split('/').slice(0,3).join('/');const newPath=location.hash.split('/').slice(0,3).join('/');await render();if(oldPath===newPath&&document.querySelector('.rc-course'))document.querySelector('#rc-learning')?.scrollIntoView();else window.scrollTo(0,0);document.querySelector('#main')?.focus({preventScroll:true});});
render();
