import {feedbackMenu,feedbackForm} from './feedback.mjs?v=20261126-public-safe-1';
import {escapeHtml as h,courseMaterials} from './course-view.mjs?v=20261127-materials-1';

const duration = minutes => `${Math.floor(minutes / 60) ? Math.floor(minutes / 60) + ' ч ' : ''}${minutes % 60} мин`;

export function recordedPage({course:c,index,enrollment:e,preview,feedbackMode=false,progress,until}) {
  const base=`#/${preview?'preview':'course'}/${c.id}`;
  let offset=0;
  const modules=c.modules.map(([title,count],moduleIndex)=>{
    const start=offset, ids=Array.from({length:count},(_,i)=>start+i);offset+=count;
    return `<details class="rc-module" ${!feedbackMode&&index>=start&&index<offset?'open':''}><summary><span class="rc-module-number">${String(moduleIndex+1).padStart(2,'0')}</span><span>${h(title)}<small data-module-progress data-lesson-ids="${ids.join(',')}">${e.done.filter(n=>ids.includes(n)).length} из ${count} пройдено</small></span><span class="rc-chevron" aria-hidden="true">⌄</span></summary><ol>${ids.map(n=>`<li><a href="${base}/${n}" ${!feedbackMode&&n===index?'aria-current="page"':''}><span class="rc-lesson-number" data-lesson-num="${n}">${e.done.includes(n)?'✓':String(n+1).padStart(2,'0')}</span><span>${count===1?'Урок '+(n+1):'Часть '+(n-start+1)}<small>${c.minutes[n]} мин</small></span>${!feedbackMode&&n===index?'<span class="rc-current">Вы здесь</span>':''}</a></li>`).join('')}</ol></details>`;
  }).join('');
  return `<div class="wrap rc-course">
    <div class="rc-breadcrumb"><a href="#/${preview?'paid':'learn'}">← ${preview?'К управлению курсами':'Моё обучение'}</a><span>${h(c.short)}</span></div>
    ${preview?'<div class="rc-preview">Предпросмотр администратора — прогресс слушателей не меняется</div>':''}
    <section class="rc-hero"><img src="../brand/group-placeholder-short.png" alt=""><div class="rc-hero-content"><p class="eyebrow">Курс в записи</p><h1>${h(c.title)}</h1><div class="rc-facts"><div><span>Эксперт курса</span><strong>${h(c.expert)}</strong></div><div><span>Программа</span><strong>${c.modules.length} модулей / ${c.lessons.length} уроков</strong></div><div><span>Время видео</span><strong>${duration(c.minutes.reduce((a,b)=>a+b,0))}</strong></div></div></div></section>
    <div class="rc-status"><div><p class="eyebrow">${preview?'В удобном темпе':'Ваш прогресс'}</p>${preview?'<p>Смотрите уроки и возвращайтесь к материалам</p>':`<div id="course-progress">${progress}</div>`}</div><div class="rc-access"><span class="rc-access-label">${preview?'Доступ к курсу':'Доступ открыт'}</span><strong>${preview?c.accessDays+' дней после оплаты':'До '+until}</strong><span>У каждого курса свой срок</span></div></div>
    <nav class="rc-nav" aria-label="Разделы курса">${(feedbackMode?[['rc-learning','Обратная связь']]:[['rc-learning','Обучение'],['rc-materials','Материалы урока'],['rc-help','Помощь']]).map(([target,label])=>`<a href="#${target}" data-action="jump-section" data-target="${target}">${label}</a>`).join('')}</nav>
    <div class="rc-layout" id="rc-learning"><aside class="rc-sidebar"><details class="rc-program" open><summary>Программа курса <span>${c.lessons.length} уроков ⌄</span></summary><div class="rc-program-body">${modules}${feedbackMenu({base,number:c.modules.length+1,feedback:e.feedback,selected:feedbackMode,recorded:true})}</div></details></aside>
    <div class="rc-workspace">${feedbackMode?feedbackForm({enrollment:e,preview,backUrl:base+'/'+(c.lessons.length-1),homeUrl:'#/'+(preview?'paid':'learn')}):`<section class="rc-panel rc-lesson"><div class="rc-lesson-meta"><span class="eyebrow">Урок ${index+1} из ${c.lessons.length}</span><span>${c.minutes[index]} мин</span></div><h2>${h(c.lessons[index])}</h2>
    <div class="rc-video-empty"><span class="rc-video-symbol" aria-hidden="true">▷</span><strong>Видеоурок</strong><p>В рабочем кабинете запись открывается здесь</p><small>Платные видео не размещены в открытом прототипе</small></div>
    <div class="rc-completion">${preview?'<span class="muted">В режиме слушателя здесь появится отметка о прохождении</span>':`<button class="btn secondary" data-action="mark-lesson" data-id="${h(e.id)}" data-index="${index}">${e.done.includes(index)?'✓ Урок пройден — отменить':'Отметить урок пройденным'}</button><p class="muted" data-progress-hint hidden></p>`}</div>
    <div class="rc-lesson-navigation">${index?`<a class="rc-previous" href="${base}/${index-1}">← Предыдущий урок</a>`:'<span></span>'}${index<c.lessons.length-1?`<a class="btn" href="${base}/${index+1}">Следующий урок →</a>`:`<a class="btn" href="${base}/feedback">Перейти к анкете →</a>`}</div></section>
    <section class="rc-panel" id="rc-materials">${courseMaterials(c)}</section>
    <section class="rc-panel rc-help" id="rc-help"><div><p class="eyebrow">Мы рядом</p><h2>Нужна помощь?</h2><p>С вопросами по доступу, видео и продлению поможет команда ВЭШ</p></div><a class="btn secondary" href="mailto:info@veshk.ru">Написать в школу ↗</a></section>
    `}</div></div></div>`;
}

export function setupRecordedPage() {
  const menu=document.querySelector('.rc-program');
  if(menu)menu.open=!matchMedia('(max-width: 860px)').matches;
}

export function refreshRecordedModules(done) {
  document.querySelectorAll('[data-module-progress]').forEach(node=>{
    const ids=node.dataset.lessonIds.split(',').map(Number);
    node.textContent=`${done.filter(id=>ids.includes(id)).length} из ${ids.length} пройдено`;
  });
}
