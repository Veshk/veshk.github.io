import {cohortValues, percentage, toggleCompleted} from './model.mjs';

const $ = (selector) => document.querySelector(selector);
const $$ = (selector) => [...document.querySelectorAll(selector)];
const courseProgress = {free:new Set(),paid:new Set()};
const state = {kind:'free',home:'active',group:'overview',lesson:0,completed:new Set(),rating:0,feedback:false,comment:'',slot:null,cohort:'free',stage:1};
let toastTimer;
function toast(text) { $('#toast').textContent=text; $('#toast').hidden=false; clearTimeout(toastTimer); toastTimer=setTimeout(()=>$('#toast').hidden=true,5000); }
function choose(container, attribute, value) { $$(container+' ['+attribute+']').forEach(button => button.setAttribute('aria-pressed',String(button.getAttribute(attribute)===String(value)))); }
function tile(kind,title,detail,href,tag='') { return `<p class="screen-category">${kind}</p><div class="mini-course"><div class="mini-art" aria-hidden="true"></div><div><strong>${title}</strong><small>${detail}</small>${tag?`<span class="status">${tag}</span>`:''}</div><a href="${href}" ${title==='Дашборды в Excel'?'data-open-paid':title==='Обратная связь'?'data-open-free':''} aria-label="Открыть пример: ${title}">↗</a></div>`; }
const homeNotes = {
 active:'Три раздела помогают различать обучение с группой и самостоятельные курсы. У каждого — свои сроки и состояние.',
 waiting:'Для закрытой учебной группы аккаунта недостаточно: администратор проверяет заявку. До одобрения материалы этой группы закрыты.',
 returning:'Повторное обучение добавляется в прежний аккаунт. Новый курс не сбрасывает сроки и прогресс уже существующих доступов.'
};
function renderHome(value) {
 state.home=value; choose('#home-choices','data-home',value); $('#home-note').textContent=homeNotes[value];
 let html='<div class="screen-title"><h3>Моё обучение</h3><button class="button outline" data-demo-add>Добавить обучение +</button></div>';
 if(value==='waiting') html+='<div class="waiting"><span class="status wait">Заявка на проверке</span><h4>Управление командой</h4><p>После проверки администратором здесь появятся материалы и расписание вашей группы.</p><p>Аккаунт создан → заявка отправлена → ожидается решение</p><button class="button outline" data-demo-approve>Показать после одобрения →</button></div>';
 else {
  if(value==='returning') html+='<div class="success-notice">Новое обучение добавлено в прежний аккаунт</div>';
  html+=tile('Программы обучения','Управление командой','Идёт обучение / учебная группа','#group');
  html+=tile('Бесплатные курсы','Обратная связь','Доступ до 20 декабря / свой прогресс','#lesson');
  html+=tile('Курсы в записи','Дашборды в Excel',value==='returning'?'Новый курс / доступ открыт':'Доступ до 15 февраля / свой прогресс','#lesson',value==='returning'?'Новое обучение':'');
 }
 $('#home-screen').innerHTML=html;
 $('[data-demo-add]').addEventListener('click',()=>{document.getElementById('access').scrollIntoView({behavior:'smooth'});toast('Для нового обучения используется тот же аккаунт. Посмотрите, как различаются основания доступа.');});
 $('[data-demo-approve]')?.addEventListener('click',()=>{renderHome('active');toast('В примере администратор одобрил заявку. Теперь доступны группа и её материалы.');});
}
const groupNotes={
 overview:'Слушатель сразу понимает, кому задать организационный вопрос. Контакт администратора и чат относятся к его группе.',
 schedule:'Есть дата, время, тема и формат. Для онлайн-занятия на странице может быть отдельный блок подключения.',
 recordings:'Пропущенное занятие можно найти по теме или модулю. Ожидаемая запись отличается от уже опубликованной.',
 materials:'Учебные материалы находятся рядом с расписанием и записями. Не нужно искать нужную ссылку в длинной переписке.',
 defense:'Если такой блок добавлен, слушатель выбирает свободное окно. В рабочем ЛК предусмотрены одиночная и парная запись, перенос и отмена.'
};
function renderGroup(value) {
 state.group=value;choose('#group-choices','data-group',value);$('#group-note').textContent=groupNotes[value];
 const screens={
 overview:'<h4>На связи с вами</h4><div class="contact-demo"><span class="avatar">М</span><div><strong>Мария, администратор</strong><small>Учебный персонаж</small></div></div><p>Поможет с расписанием, материалами и организацией обучения.</p><button class="button outline" data-contact>Как связаться →</button>',
 schedule:'<h4>Расписание группы</h4><p>Время Екатеринбурга (UTC+5)</p><div class="demo-row"><span>30 нояб.<br>18:00–21:00</span><div><strong>Мотивация и обратная связь</strong><small>Онлайн</small></div></div><div class="demo-row"><span>3 дек.<br>18:00–21:00</span><div><strong>Управление конфликтами</strong><small>Онлайн</small></div></div><div class="demo-row"><span>7 дек.<br>18:00–21:00</span><div><strong>Практика руководителя</strong><small>Онлайн</small></div></div>',
 recordings:'<h4>Записи занятий</h4><details open><summary>Модуль 1. Работа с командой</summary><div class="demo-row"><div><strong>Постановка задач</strong><small>Запись опубликована</small></div><button data-recording>Открыть ↗</button></div><div class="demo-row"><div><strong>Мотивация и обратная связь</strong><small>Запись ожидается</small></div><span>Ожидается</span></div></details>',
 materials:'<h4>Материалы для обучения</h4><div class="demo-row"><span>Памятка</span><div><strong>Подготовка к занятию</strong><small>Учебный пример</small></div><button data-material>Посмотреть →</button></div><div id="material-preview" hidden><p>Подумайте о недавнем разговоре с коллегой. Что вы хотели изменить? Какой результат получили? Возьмите этот пример на занятие.</p></div><div class="demo-row"><span>Шаблон</span><div><strong>План обратной связи</strong><small>В рабочем ЛК здесь может быть файл или ссылка</small></div></div>',
 defense:`<h4>Запись на защиту курсовой</h4><p>10 декабря / время Екатеринбурга</p><div class="slot-grid" style="margin-top:16px">${['18:00–18:20','18:30–18:50'].map((time,i)=>`<button data-slot="${i}" class="${state.slot===i?'selected':''}" aria-pressed="${state.slot===i}"><strong>${time}</strong>${state.slot===i?'Вы записаны ✓':'Свободно →'}</button>`).join('')}</div><p class="slot-message" role="status">${state.slot===null?'Выберите окно, чтобы попробовать запись.':'Запись сохранена только в этом примере. Нажмите своё окно ещё раз для отмены.'}</p>`
 };
 $('#group-screen').innerHTML=screens[value];
 $('[data-contact]')?.addEventListener('click',()=>toast('В рабочем кабинете здесь открывается выбранный администратором способ связи. В примере сообщение не отправляется.'));
 $('[data-recording]')?.addEventListener('click',()=>toast('Опубликованная запись ведёт к учебному видео. Ожидаемая запись пока недоступна. Здесь показана разница статусов.'));
 $('[data-material]')?.addEventListener('click',()=>{const block=$('#material-preview');block.hidden=!block.hidden;$('[data-material]').textContent=block.hidden?'Посмотреть →':'Свернуть ↑';});
 $$('[data-slot]').forEach(button=>button.addEventListener('click',()=>{const id=Number(button.dataset.slot);state.slot=state.slot===id?null:id;renderGroup('defense');$(`[data-slot="${id}"]`).focus({preventScroll:true});}));
}
const freeLessons=[
 {title:'Зачем нужна обратная связь',heading:'Помочь человеку увидеть следующий шаг',text:'Обсудите конкретную ситуацию и её результат. Отделите наблюдаемые действия от предположений о человеке.'},
 {title:'Как построить разговор',heading:'Факт → влияние → договорённость',text:'Что произошло? Как это повлияло на работу? Что мы договоримся сделать по-другому? Это короткий учебный пример.'},
 {title:'Практика и договорённости',heading:'Закончить разговор ясным действием',text:'Сформулируйте следующий шаг, срок и способ проверить результат. Убедитесь, что договорённость понята одинаково.'}
];
const paidLessons=[
 {title:'Из данных — в показатели',heading:'Выбрать вопрос, на который отвечает отчёт',text:'Какие показатели помогают принять решение? В учебном примере определим цель будущего дашборда.'},
 {title:'Подготовка таблицы',heading:'Проверить структуру и качество данных',text:'Привести даты и числа к единому формату, убрать дубли и проверить пропуски перед расчётом показателей.'},
 {title:'Собираем дашборд',heading:'Показать главное и оставить возможность разобраться',text:'Собрать показатели, фильтры и графики. Подписать период, единицы измерения и источник данных.'}
];
function setKind(kind){state.kind=kind;state.completed=courseProgress[kind];state.lesson=0;state.feedback=false;state.rating=0;state.comment='';renderLesson();}
function renderLesson() {
 const lessons=state.kind==='paid'?paidLessons:freeLessons;
 choose('#lesson-kind-choices','data-kind',state.kind);
 $('#lesson-kind-label').textContent=(state.kind==='paid'?'Курс в записи':'Бесплатный курс')+' / Учебный пример';
 $('#lesson-course-title').textContent=state.kind==='paid'?'Дашборды в Excel':'Обратная связь в команде';
 const progress=percentage(state.completed.size,lessons.length);
 $('#progress-label').textContent=`Пройдено ${progress}%`;$('#lesson-progress').style.width=progress+'%';$('.progress-track').setAttribute('aria-valuenow',String(progress));
 $('#lesson-choices').innerHTML=lessons.map((lesson,i)=>`<button data-lesson="${i}" aria-pressed="${state.lesson===i}"><span>0${i+1}</span><span>${lesson.title}</span><span class="${state.completed.has(i)?'completed':''}" aria-label="${state.completed.has(i)?'Пройден':'Не пройден'}">${state.completed.has(i)?'✓':'—'}</span></button>`).join('')+`<button data-lesson="feedback" aria-pressed="${state.lesson==='feedback'}"><span>04</span><span>Анкета обратной связи</span><span>${state.feedback?'✓':'→'}</span></button>`;
 $$('[data-lesson]').forEach(button=>button.addEventListener('click',()=>{state.lesson=button.dataset.lesson==='feedback'?'feedback':Number(button.dataset.lesson);renderLesson();$(`[data-lesson="${state.lesson}"]`).focus({preventScroll:true});}));
 if(state.lesson==='feedback'){renderFeedback();return;}
 const lesson=lessons[state.lesson];
 $('#lesson-screen').innerHTML=`<p class="eyebrow">Урок ${state.lesson+1} из 3</p><h4>${lesson.title}</h4><div class="lesson-visual"><span class="lesson-symbol" aria-hidden="true">0${state.lesson+1}</span><div><h5>${lesson.heading}</h5><p>${lesson.text}</p></div></div><button class="button ${state.completed.has(state.lesson)?'outline':''}" id="complete-lesson">${state.completed.has(state.lesson)?'Снять отметку о прохождении':'Отметить пройденным ✓'}</button><small class="demo-caption">Вместо учебного видео здесь короткий пример. В ЛК урок может содержать видео и материалы.</small>`;
 $('#complete-lesson').addEventListener('click',()=>{state.completed=toggleCompleted(state.completed,state.lesson);courseProgress[state.kind]=state.completed;renderLesson();$('#complete-lesson').focus({preventScroll:true});});
}
function renderFeedback(){
 $('#lesson-screen').innerHTML=`<p class="eyebrow">Анкета обратной связи</p><h4>Как прошло обучение?</h4>${state.feedback?'<p class="form-success" role="status">Ответ сохранён в учебном примере. В реальном ЛК он поступает команде курса.</p>':''}<form class="feedback-form" id="feedback-form"><label>Ваша оценка</label><div class="rating" role="group" aria-label="Оценка курса">${[1,2,3,4,5].map(n=>`<button type="button" data-rating="${n}" aria-label="${n} из 5" aria-pressed="${state.rating===n}">${n}</button>`).join('')}</div><label for="feedback-comment">Что было полезно?</label><textarea id="feedback-comment" maxlength="600" placeholder="Напишите учебный комментарий"></textarea><p id="feedback-error" class="form-error" role="alert"></p><button class="button" type="submit">${state.feedback?'Обновить учебный ответ':'Отправить учебный ответ'}</button><small class="demo-caption">Ответ остаётся только на этой странице. При перезагрузке он исчезнет.</small></form>`;
 $('#feedback-comment').value=state.comment;
 $('#feedback-comment').addEventListener('input',e=>state.comment=e.target.value);
 $$('[data-rating]').forEach(button=>button.addEventListener('click',()=>{state.rating=Number(button.dataset.rating);choose('.rating','data-rating',state.rating);$('#feedback-error').textContent='';}));
 $('#feedback-form').addEventListener('submit',e=>{e.preventDefault();if(!state.rating){$('#feedback-error').textContent='Выберите оценку от 1 до 5.';return;}state.feedback=true;renderLesson();$('#feedback-form button[type=submit]').focus({preventScroll:true});});
}
const flows={
 program:{steps:[['Заявка на группу','Слушатель регистрируется или использует свой прежний аккаунт.'],['Проверка администратором','Сотрудник проверяет, что слушатель относится к выбранной группе.'],['Открытие доступа','После одобрения появляется учебная страница группы.'],['Обучение','Расписание, записи, материалы и срок доступа своей группы.']],note:'Выбор группы при регистрации не заменяет проверку. На повторное обучение не нужно создавать новый аккаунт. Общий срок и личное продление учитываются отдельно.'},
 free:{steps:[['Запись на курс','Заявка с лендинга относится к конкретному бесплатному курсу.'],['Назначение курса','Подключённая интеграция или сотрудник открывает персональный доступ.'],['Вход в кабинет','Новый слушатель получает аккаунт; для знакомого используется прежний.'],['Уроки и прогресс','Бесплатный курс появляется в своём разделе, с личным сроком.']],note:'Бесплатный курс тоже имеет персональный доступ и срок. Заявка не открывает другие курсы. Автоматическая выдача работает только там, где цепочка приёма заявок подключена и проверена.'},
 paid:{steps:[['Заказ на курс','Человек выбирает курс в записи на лендинге.'],['Проверка оплаты','Основание для оплаченного доступа — подтверждённый платёж.'],['Персональный доступ','Курс назначается нужному человеку, со своим сроком.'],['Курс в записи','В кабинете слушателя используется именно эта подпись.']],note:'Созданный заказ сам по себе не означает оплату. В админке такие курсы называются «Платные курсы», у слушателя — «Курсы в записи». Интеграцию заказа, оплаты и доступа проверяют перед запуском.'}
};
function renderAccess(value){choose('#access-choices','data-access',value);$('#access-flow').innerHTML=flows[value].steps.map(([title,text],i)=>`<div class="flow-step"><span>0${i+1}</span><h3>${title}</h3><p>${text}</p></div>`).join('');$('#access-note').textContent=flows[value].note;}
const teamCopy={
 sales:[['Объяснить, куда человек попадёт','Показывайте «Моё обучение»: программы, бесплатные курсы и курсы в записи находятся в одном аккаунте.'],['Различать заявку, оплату и доступ','Уточняйте конкретный курс и состояние. Зарегистрироваться, оплатить и получить доступ — разные события.'],['В перспективе видеть продолжение пути','Какие бесплатные курсы связаны с последующими покупками и повторным обучением. Для этого нужен отдельный отчёт.']],
 admin:[['Проверить и открыть доступ','Разобрать заявки на группы, найти слушателя и его обучение, проверить общий срок и персональные продления.'],['Поддерживать страницу группы','Обновить расписание, записи и материалы. Общие блоки позволяют поддерживать одинаковое содержание в нескольких группах.'],['Обратиться к слушателю и услышать ответ','Личные уведомления в ЛК, объявления группы и обратная связь самостоятельных курсов помогают сопровождать обучение.']]
};
function renderTeam(value){choose('#team-choices','data-team',value);$('#team-copy').innerHTML='<ol class="team-list">'+teamCopy[value].map(([title,text],i)=>`<li><span>0${i+1}</span><div><h3>${title}</h3><p>${text}</p></div></li>`).join('')+'</ol>';}
const scenarioCopy={account:'Уточните email, с которым выдан доступ. Если человек вошёл под другим аккаунтом, нужного обучения там может не быть. Помогите войти в прежний профиль.',access:'Проверьте именно нужную группу или курс. Для закрытой группы заявка может ещё ожидать проверки; для платного курса отдельно проверяются оплата и открытие доступа.',date:'Сверьте срок конкретного обучения. Истёкший доступ не означает удаление аккаунта. Вопрос продления решается по правилам курса; другие доступы сохраняют свои сроки.'};
const stageNames=['Доступ открыт','Отметили хотя бы один урок','Отметили не меньше половины','Отметили все уроки'];
function renderAnalytics(){
 choose('#analytics-choices','data-cohort',state.cohort);const values=cohortValues(state.cohort);
 $('#funnel').innerHTML=values.map((n,i)=>`<button class="funnel-row" data-stage="${i}" aria-pressed="${state.stage===i}" aria-label="${stageNames[i]}: ${n}, ${percentage(n,values[0])}% от открытых доступов"><span class="funnel-text"><span>${stageNames[i]}</span><strong>${n}<small>${percentage(n,values[0])}%</small></strong></span><span class="bar-track"><span style="width:${percentage(n,values[0])}%"></span></span></button>`).join('');
 $('#completion-rate').textContent=percentage(values[3],values[0])+'%';$('#cohort-summary').textContent=`${values[3]} из ${values[0]} зачислений с отметками всех уроков за первые 30 дней. Это учебные данные.`;
 const insights=[`Открыли ${values[0]} доступов. Это база расчёта; выданный доступ ещё не означает начало обучения.`,`${values[0]-values[1]} из ${values[0]} доступов без первой отметки. Это повод проверить вход и понятность первого шага, а не автоматически считать человека незаинтересованным.`,`${values[2]} зачислений достигли половины курса. Можно изучить темы, на которых люди останавливаются, если накоплена история отметок.`,`${values[3]} зачислений с отметками всех уроков. Дальше можно сопоставить обратную связь и дальнейшее обучение — при наличии нужных данных.`];
 $('#stage-insight').textContent=insights[state.stage];
 $$('[data-stage]').forEach(button=>button.addEventListener('click',()=>{state.stage=Number(button.dataset.stage);renderAnalytics();$(`[data-stage="${state.stage}"]`).focus({preventScroll:true});}));
}
$('#lesson-kind-choices').addEventListener('click',e=>{const b=e.target.closest('[data-kind]');if(b)setKind(b.dataset.kind);});
document.addEventListener('click',e=>{if(e.target.closest('[data-open-paid]'))setKind('paid');if(e.target.closest('[data-open-free]'))setKind('free');});
$('#home-choices').addEventListener('click',e=>{const b=e.target.closest('[data-home]');if(b)renderHome(b.dataset.home);});
$('#group-choices').addEventListener('click',e=>{const b=e.target.closest('[data-group]');if(b)renderGroup(b.dataset.group);});
$('#access-choices').addEventListener('click',e=>{const b=e.target.closest('[data-access]');if(b)renderAccess(b.dataset.access);});
$('#team-choices').addEventListener('click',e=>{const b=e.target.closest('[data-team]');if(b)renderTeam(b.dataset.team);});
$('#scenario-choices').addEventListener('click',e=>{const b=e.target.closest('[data-scenario]');if(!b)return;choose('#scenario-choices','data-scenario',b.dataset.scenario);$('#scenario-result').textContent=scenarioCopy[b.dataset.scenario];});
$('#analytics-choices').addEventListener('click',e=>{const b=e.target.closest('[data-cohort]');if(!b)return;state.cohort=b.dataset.cohort;renderAnalytics();});
function reset(){courseProgress.free=new Set();courseProgress.paid=new Set();Object.assign(state,{kind:'free',home:'active',group:'overview',lesson:0,completed:new Set(),rating:0,feedback:false,comment:'',slot:null,cohort:'free',stage:1});renderHome('active');renderGroup('overview');renderLesson();renderAccess('program');renderTeam('sales');renderAnalytics();choose('#scenario-choices','data-scenario','');$('#scenario-result').textContent='Проверка начинается с аккаунта, конкретного курса и статуса доступа. Повторная регистрация не должна быть первым решением.';}
$('#reset-demo').addEventListener('click',()=>{reset();toast('Все учебные примеры сброшены. Можно пройти рассказ заново.');});
reset();

const scenes=$$('.scene');let current=0;let scheduled=false;
function updateDeck(){
 const point=Math.min(innerHeight*.38,300);let best=0;
 scenes.forEach((scene,i)=>{if(scene.getBoundingClientRect().top<=point)best=i;});current=best;
 $('#scene-count').textContent=String(current+1).padStart(2,'0')+' / '+String(scenes.length).padStart(2,'0');$('#deck-label').textContent=scenes[current].dataset.title;$('#deck-progress').style.width=(current+1)/scenes.length*100+'%';$('#prev-scene').disabled=current===0;$('#next-scene').disabled=current===scenes.length-1;
}
function go(delta){const next=Math.min(scenes.length-1,Math.max(0,current+delta));scenes[next].scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'});}
$('#prev-scene').addEventListener('click',()=>go(-1));$('#next-scene').addEventListener('click',()=>go(1));
addEventListener('scroll',()=>{if(scheduled)return;scheduled=true;requestAnimationFrame(()=>{updateDeck();scheduled=false;});},{passive:true});addEventListener('resize',updateDeck);
function presentation(on){document.body.classList.toggle('presentation',on);$('#present').setAttribute('aria-pressed',String(on));$('#present').innerHTML=(on?'Завершить показ':'Режим показа')+' <span aria-hidden="true">⤢</span>';if(on)toast('Режим показа: главы переключаются стрелками ← →. Выйти — Escape.');}
$('#present').addEventListener('click',()=>presentation(!document.body.classList.contains('presentation')));
addEventListener('keydown',e=>{if(!document.body.classList.contains('presentation'))return;if(e.key==='Escape'){presentation(false);return;}if(e.target.closest('input,textarea,select,a,summary,[contenteditable]'))return;if(e.target.closest('button')&&!e.target.closest('#present,#prev-scene,#next-scene'))return;if(e.key==='ArrowRight'||e.key==='ArrowLeft'){e.preventDefault();go(e.key==='ArrowRight'?1:-1);}});
updateDeck();
