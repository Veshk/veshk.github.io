'use strict';
window.CourseOverview = (()=>{
  const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const safeUrl=value=>{try{const u=new URL(value);return ['http:','https:'].includes(u.protocol)?u.href:'';}catch{return '';}};
  const safeEmail=value=>/^[a-z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-z0-9-]+(?:\.[a-z0-9-]+)+$/i.test(value||'')?value:'';
  const safePhoto=value=>/^data:image\/(jpeg|png|webp);base64,[A-Za-z0-9+/=]+$/.test(value||'')?value:safeUrl(value);
  const dateLabel=value=>value.split('-').reverse().join('.');
  const minutes=value=>{const m=String(value||'').match(/^(\d{1,2})[:.](\d{2})\s*[-–—]\s*(\d{1,2})[:.](\d{2})$/);return m&&+m[1]<24&&+m[3]<24&&+m[2]<60&&+m[4]<60?[+m[1]*60+(+m[2]),+m[3]*60+(+m[4])]:null;};
  const localNow=now=>{
    const p=Object.fromEntries(new Intl.DateTimeFormat('en-GB',{timeZone:'Asia/Yekaterinburg',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hourCycle:'h23'}).formatToParts(now).map(x=>[x.type,x.value]));
    return {date:`${p.year}-${p.month}-${p.day}`,minute:+p.hour*60+(+p.minute)};
  };
  function upcoming(blocks,now=new Date()){
    const clock=localNow(now),rows=[];
    blocks.forEach((b,index)=>{if(b.type==='schedule'&&b.data.visible!==false)(b.data.lessons||[]).forEach(l=>{
      if(!/^\d{4}-\d{2}-\d{2}$/.test(l.date||'')||Number.isNaN(Date.parse(l.date+'T00:00:00Z'))||new Date(l.date+'T00:00:00Z').toISOString().slice(0,10)!==l.date)return;
      rows.push({...l,show_moscow:!!b.data.show_moscow,section:index});
    });});
    rows.sort((a,b)=>a.date.localeCompare(b.date)||(minutes(a.time)?.[0]??1440)-(minutes(b.time)?.[0]??1440));
    const next=rows.find(l=>l.date>clock.date||(l.date===clock.date&&(!minutes(l.time)||minutes(l.time)[1]>clock.minute)));
    return next?rows.filter(l=>l.date===next.date):[];
  }
  const lessonHighlights={day:'Другой день',time:'Другое время',format:'Другой формат',important:'Важно'};
  const lessonHighlight=l=>l.highlight?(lessonHighlights[l.highlight_type]||lessonHighlights.important):'';
  const assignedAdvisorId=(group,programs={})=>group.academic_advisor_id||programs[group.program_id]?.academic_advisor_id||null;
  function markup(blocks,group,advisors={},now=new Date(),programs={}){
    const lessons=upcoming(blocks,now),advisor=group.show_academic_advisor===false?null:advisors[assignedAdvisorId(group,programs)],email=safeEmail(advisor?.email),photo=safePhoto(advisor?.photo),firstSchedule=blocks.findIndex(b=>b.type==='schedule'&&b.data.visible!==false);
    const next=`<section class="upcoming-card" aria-labelledby="upcoming-title"><div class="upcoming-heading"><h2 id="upcoming-title">${lessons.length>1?'Ближайшие занятия':'Ближайшее занятие'}</h2></div>${lessons.length?`<div class="upcoming-lessons">${lessons.map(l=>`<article class="upcoming-lesson${lessonHighlight(l)?' lesson-highlighted':''}">${lessonHighlight(l)?`<span class="lesson-highlight-label">${esc(lessonHighlight(l))}</span>`:''}<div class="upcoming-datetime"><time datetime="${esc(l.date)}">${dateLabel(l.date)}</time><div class="upcoming-time"><span>${esc(l.time||'Время уточняется')}${l.time?' ЕКБ':''}</span>${l.show_moscow&&l.time?`<span>${esc(l.time.replace(/(\d{1,2})[:.](\d{2})/g,(_,h,m)=>String((+h+22)%24).padStart(2,'0')+':'+m))} МСК</span>`:''}</div></div><h3>${esc(l.title)}</h3>${String(l.comment||'').trim()?`<p class="lesson-comment">${esc(l.comment)}</p>`:''}<div class="upcoming-meta"><span>${esc(l.format||group.format)}</span>${l.teacher?`<span>${esc(l.teacher)}</span>`:''}${safeUrl(l.url)?`<a href="${esc(safeUrl(l.url))}" target="_blank" rel="noopener noreferrer">Подключиться ↗</a>`:''}</div></article>`).join('')}</div>`:`<p class="upcoming-empty">${group.graduates_at&&group.graduates_at<=localNow(now).date?'Обучение завершено':'Новые занятия пока не запланированы.'}</p>`}${firstSchedule>=0?`<a class="upcoming-schedule" href="#block-${firstSchedule}">Всё расписание <span aria-hidden="true">↓</span></a>`:''}</section>`;
    const person=advisor?.name?`<section class="advisor-card" aria-label="Научный руководитель"><div class="eyebrow muted">Научный руководитель</div><div class="advisor-person">${photo?`<img src="${esc(photo)}" alt="${esc(advisor.name)}" referrerpolicy="no-referrer">`:`<span class="advisor-initials" aria-hidden="true">${esc(advisor.name.split(/\s+/).slice(0,2).map(x=>x[0]).join(''))}</span>`}<div><h2>${esc(advisor.name)}</h2><button type="button" class="btn advisor-question-button" data-advisor-question-open>Задать вопрос</button><p class="advisor-question-status" data-advisor-question-status role="status"></p></div></div></section>`:'';
    return `<div class="learning-overview${person?' has-advisor':''}" id="learning-overview">${next}${person}</div>`;
  }
  function render(ctx,group,blocks){
    document.querySelector('#learning-overview')?.remove();
    const hero=document.querySelector('.course-hero');hero.classList.add('has-learning-overview');
    hero.insertAdjacentHTML('afterend',markup(blocks,group,ctx.state?.academicAdvisors,new Date(),ctx.programs));
    document.getElementById('advisor-question-dialog')?.remove();
    const advisor=ctx.state?.academicAdvisors?.[assignedAdvisorId(group,ctx.programs)];
    if(!advisor||group.show_academic_advisor===false)return;
    const query=new URLSearchParams(location.search),readonly=query.has('preview')||query.has('student_view')||query.has('as_student');
    document.body.insertAdjacentHTML('beforeend',`<dialog id="advisor-question-dialog" class="advisor-question-dialog" aria-labelledby="advisor-question-title">
      <button type="button" class="question-close" data-advisor-question-close aria-label="Закрыть">×</button>
      <h2 id="advisor-question-title">Вопрос научному руководителю</h2><p class="muted">${esc(advisor.name)}</p>
      <form data-advisor-question-form data-demo="true" data-advisor="${esc(advisor.name)}" data-group="${esc(group.code||group.name)}"${readonly?' data-readonly':''}>
        <label for="advisor-question-text">Ваш вопрос</label><textarea id="advisor-question-text" name="question" required maxlength="5000" placeholder="Напишите, что хотите уточнить"${readonly?' disabled':''}></textarea>
        <p class="advisor-question-hint">${readonly?'Это просмотр как студент. Отправка недоступна':''}</p>
        <p class="advisor-question-hint">Прототип — вопрос сохраняется только в этом браузере</p><p class="advisor-question-error" data-question-error role="alert"></p>
        <div class="advisor-question-actions"><button type="submit" class="btn"${readonly?' disabled':''}>Отправить вопрос</button><button type="button" class="btn outline" data-advisor-question-close>Отмена</button></div>
      </form></dialog>`);
  }
  function editor({root,record,ctx,mark}){
    const section=document.createElement('details');section.className='editor-block advisor-editor';
    const inherited=()=>ctx.state.academicAdvisors?.[ctx.programs[record.program_id]?.academic_advisor_id]?.name||'не назначен';
    const choices=()=>`<option value="">Из программы: ${esc(inherited())}</option>`+Object.entries(ctx.state.academicAdvisors||{}).map(([id,p])=>`<option value="${esc(id)}">${esc(p.name)}</option>`).join('');
    section.innerHTML=`<summary>Научный руководитель</summary><div class="editor-block-body"><label class="check"><input type="checkbox" data-advisor-visible ${record.show_academic_advisor!==false?'checked':''}>Показывать научного руководителя</label><p>По умолчанию используется руководитель программы. Можно скрыть карточку или выбрать другого только для этой группы.</p><label class="field">Научный руководитель этой группы<select data-advisor-select>${choices()}</select></label><p class="form-note">Выберите «Из программы», чтобы вернуться к общей настройке.</p><a class="text-link" href="admin.html?role=${encodeURIComponent(ctx.role)}&panel=advisors" target="_blank" rel="noopener">Открыть справочник научных руководителей ↗</a></div>`;
    root.prepend(section);const select=section.querySelector('select'),visible=section.querySelector('[data-advisor-visible]');select.value=record.academic_advisor_id||'';select.addEventListener('change',mark);visible.addEventListener('change',mark);
    const refresh=document.createElement('button');refresh.type='button';refresh.className='text-button';refresh.textContent='Обновить список';refresh.addEventListener('click',()=>document.dispatchEvent(new CustomEvent('refresh-academic-advisors')));section.querySelector('.editor-block-body').append(refresh);
    document.addEventListener('academic-advisors-changed',()=>{const chosen=select.value;select.innerHTML=choices();select.value=chosen;});
    return {save(){record.academic_advisor_id=select.value||null;record.show_academic_advisor=visible.checked;}};
  }
  return {upcoming,markup,render,editor,safeEmail,safePhoto,assignedAdvisorId};
})();
