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
  function markup(blocks,group,advisors={},now=new Date()){
    const lessons=upcoming(blocks,now),advisor=advisors[group.academic_advisor_id],email=safeEmail(advisor?.email),photo=safePhoto(advisor?.photo),firstSchedule=blocks.findIndex(b=>b.type==='schedule'&&b.data.visible!==false);
    const next=`<section class="upcoming-card" aria-labelledby="upcoming-title"><div class="upcoming-heading"><h2 id="upcoming-title">${lessons.length>1?'Ближайшие занятия':'Ближайшее занятие'}</h2>${lessons.length?`<time datetime="${esc(lessons[0].date)}">${dateLabel(lessons[0].date)}</time>`:''}</div>${lessons.length?`<div class="upcoming-lessons">${lessons.map(l=>`<article class="upcoming-lesson"><h3>${esc(l.title)}</h3><div class="upcoming-meta"><span>${esc(l.time||'Время уточняется')}${l.time?' ЕКБ':''}</span>${l.show_moscow&&l.time?`<span>${esc(l.time.replace(/(\d{1,2})[:.](\d{2})/g,(_,h,m)=>String((+h+22)%24).padStart(2,'0')+':'+m))} МСК</span>`:''}<span>${esc(l.format||group.format)}</span>${l.teacher?`<span>${esc(l.teacher)}</span>`:''}${safeUrl(l.url)?`<a href="${esc(safeUrl(l.url))}" target="_blank" rel="noopener noreferrer">Подключиться ↗</a>`:''}</div></article>`).join('')}</div>`:`<p class="upcoming-empty">${group.graduates_at&&group.graduates_at<=localNow(now).date?'Обучение завершено':'Новые занятия пока не запланированы.'}</p>`}${firstSchedule>=0?`<a class="upcoming-schedule" href="#block-${firstSchedule}">Всё расписание <span aria-hidden="true">↓</span></a>`:''}</section>`;
    const person=advisor?.name?`<section class="advisor-card" aria-label="Научный руководитель"><div class="eyebrow muted">Научный руководитель</div><div class="advisor-person">${photo?`<img src="${esc(photo)}" alt="${esc(advisor.name)}" referrerpolicy="no-referrer">`:`<span class="advisor-initials" aria-hidden="true">${esc(advisor.name.split(/\s+/).slice(0,2).map(x=>x[0]).join(''))}</span>`}<div><h2>${esc(advisor.name)}</h2>${email?`<a class="advisor-email" href="mailto:${esc(encodeURIComponent(email).replace('%40','@'))}">${esc(email)}</a>`:'<p class="muted">Почта будет добавлена</p>'}</div></div></section>`:'';
    return `<div class="learning-overview${person?' has-advisor':''}" id="learning-overview">${next}${person}</div>`;
  }
  function render(ctx,group,blocks){
    document.querySelector('#learning-overview')?.remove();
    const hero=document.querySelector('.course-hero');hero.classList.add('has-learning-overview');
    hero.insertAdjacentHTML('afterend',markup(blocks,group,ctx.state?.academicAdvisors));
  }
  function editor({root,record,ctx,mark}){
    const section=document.createElement('details');section.className='editor-block advisor-editor';
    section.innerHTML=`<summary>Научный руководитель</summary><div class="editor-block-body"><p>Компактная карточка на первом экране. Выберите профиль из справочника.</p><label class="field">Руководитель группы<select data-advisor-select><option value="">Не показывать</option>${Object.entries(ctx.state.academicAdvisors||{}).map(([id,p])=>`<option value="${esc(id)}">${esc(p.name)}</option>`).join('')}</select></label><a class="text-link" href="admin.html?role=${encodeURIComponent(ctx.role)}&panel=advisors" target="_blank" rel="noopener">Открыть справочник научных руководителей ↗</a></div>`;
    root.prepend(section);const select=section.querySelector('select');select.value=record.academic_advisor_id||'';select.addEventListener('change',mark);
    const refresh=document.createElement('button');refresh.type='button';refresh.className='text-button';refresh.textContent='Обновить список';refresh.addEventListener('click',()=>document.dispatchEvent(new CustomEvent('refresh-academic-advisors')));section.querySelector('.editor-block-body').append(refresh);
    document.addEventListener('academic-advisors-changed',()=>{const chosen=select.value;select.innerHTML='<option value="">Не показывать</option>'+Object.entries(ctx.state.academicAdvisors||{}).map(([id,p])=>`<option value="${esc(id)}">${esc(p.name)}</option>`).join('');select.value=chosen;});
    return {save(){record.academic_advisor_id=select.value||null;}};
  }
  return {upcoming,markup,render,editor,safeEmail,safePhoto};
})();
