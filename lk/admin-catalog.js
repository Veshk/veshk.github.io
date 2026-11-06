'use strict';
window.AdminCatalog={init(ctx){
  const esc=ctx.escape;
  const badge=(label,tone='gray')=>`<span class="group-status status-${tone}"><span aria-hidden="true"></span>${esc(label)}</span>`;
  const action=(label,type,id,primary=false)=>`<button class="group-action${primary?' group-action-primary':''}" data-catalog-action="${type}" data-id="${esc(id)}">${label}</button>`;
  const value=id=>document.getElementById(id).value;
  const opts=entries=>entries.map(([id,label])=>`<option value="${esc(id)}">${esc(label)}</option>`).join('');
  const people=opts(Object.entries(ctx.names));
  for(const panel of ['requests','programs']){
    const root=document.getElementById(panel+'-panel'),filters=root.querySelector('.filters'),scroll=root.querySelector('.schedule-scroll');
    const workspace=document.createElement('div');workspace.className='group-workspace';filters.before(workspace);workspace.append(filters);workspace.append(scroll);
    filters.className='group-filters directory-filters';scroll.className='group-grid-scroll';scroll.tabIndex=0;scroll.setAttribute('role','region');scroll.setAttribute('aria-label',panel==='requests'?'Таблица заявок':'Таблица программ');scroll.querySelector('table').className='group-grid';
    const bar=document.createElement('div');bar.className='group-toolbar';bar.innerHTML=`<span>${panel==='requests'?'Имя слушателя открывает заявку':'Название открывает карточку программы'}</span><span id="${panel}-count" role="status"></span>`;scroll.before(bar);
  }
  document.getElementById('search').placeholder='Имя, почта, группа или ТЕГ';
  document.querySelector('#requests-panel .group-filters').insertAdjacentHTML('beforeend',`<label class="field">Администратор<select id="request-owner"><option value="">Все администраторы</option><option value="unassigned">Без группы</option>${people}</select></label><label class="field">Обработка<select id="request-scope"><option value="">Все доступные заявки</option><option value="mine">Могу обработать</option></select></label>`);
  document.querySelector('#programs-panel .group-filters').insertAdjacentHTML('beforeend','<label class="field">Группы<select id="program-presence"><option value="">Все программы</option><option value="with">С группами</option><option value="without">Без групп</option></select></label>');
  document.querySelector('#programs-panel .group-filters').insertAdjacentHTML('afterbegin',`<label class="field">Список<select id="program-source-filter"><option value="catalog">Добавленные из каталога школы</option><option value="">Все записи справочника</option></select></label>`);
  document.querySelector('#programs-panel .group-filters').insertAdjacentHTML('beforeend',`<label class="field">Тип программы<select id="program-type-filter"><option value="">Все типы</option>${opts(SchoolPrograms.types.map(t=>[t,SchoolPrograms.typeLabel(t)]))}</select></label><label class="field">Формат<select id="program-format-filter"><option value="">Все форматы</option><option value="онлайн">Онлайн</option><option value="офлайн">Очно</option></select></label>`);
  const students=document.createElement('section');students.id='students-panel';students.hidden=true;document.querySelector('#demo-notice').before(students);
  students.innerHTML=`<div id="student-group-context" class="participant-context" hidden></div><div class="group-workspace"><div class="group-filters"><label class="field">Поиск слушателя<input id="student-search" type="search" placeholder="Имя или почта"></label><label class="field">Группа<select id="student-group"><option value="">Все группы</option></select></label><label class="field">Доступ<select id="student-status"><option value="">Любой статус</option><option value="active">Открыт</option><option value="closed">Завершён</option><option value="pending">Не открыт</option></select></label><label class="field">Посещения ЛК<select id="student-visits"><option value="">Все слушатели</option><option value="visited">Есть посещения</option><option value="never">Ещё не посещал</option></select></label></div><div class="group-toolbar"><span>Аккаунты слушателей · время Екатеринбурга, UTC+5</span><span id="students-count" role="status"></span></div><div class="group-grid-scroll" tabindex="0" role="region" aria-label="Таблица слушателей"><table class="group-grid"><thead></thead><tbody id="students-body"></tbody></table></div></div><p class="group-footnote">Посещения учитываются и при сохранённой авторизации. В демонстрации слушатели и история вымышлены.</p>`;
  const dialog=document.createElement('dialog');dialog.id='student-dialog';dialog.setAttribute('aria-labelledby','student-title');document.body.append(dialog);
  const sorts={requests:{key:'name',dir:1},programs:{key:'title',dir:1},students:{key:'name',dir:1}};
  const head=(kind,columns)=>{const sort=sorts[kind];document.querySelector(`#${kind}-panel thead`).innerHTML='<tr>'+columns.map(([key,label])=>`<th scope="col"${sort.key===key?` aria-sort="${sort.dir===1?'ascending':'descending'}"`:''}>${key?`<button data-directory-sort="${kind}" data-key="${key}">${label}<span aria-hidden="true">${sort.key===key?(sort.dir===1?'↑':'↓'):'↕'}</span></button>`:label}</th>`).join('')+'</tr>';};
  const sorted=(rows,kind)=>rows.sort((a,b)=>String(a[sorts[kind].key]||'').localeCompare(String(b[sorts[kind].key]||''),'ru',{numeric:true})*sorts[kind].dir);
  const count=(kind,n,total)=>document.getElementById(kind+'-count').textContent=`Показано ${n} из ${total}`;
  const empty=n=>`<tr><td class="group-empty" colspan="${n}"><strong>Ничего не найдено</strong><p>Измените поиск или фильтры.</p></td></tr>`;
  function renderPrograms(){
    const all=Object.entries(ctx.programs).map(([id,p])=>({...p,id,count:Object.values(ctx.groups).filter(g=>g.program_id===id).length}));
    const selected=all.filter(p=>!value('program-source-filter')||p.source_id);
    const rows=sorted(selected.filter(p=>(p.title+' '+p.tag).toLocaleLowerCase('ru').includes(value('program-search').trim().toLocaleLowerCase('ru'))&&(!value('program-section-filter')||p.section===value('program-section-filter'))&&(!value('program-type-filter')||p.program_type===value('program-type-filter'))&&(!value('program-format-filter')||(p.formats||[]).includes(value('program-format-filter')))&&(!value('program-presence')||(value('program-presence')==='with'?p.count>0:p.count===0))),'programs');
    head('programs',[['title','Программа'],['program_type','Тип и формат'],['section','Раздел'],['tag','ТЕГ'],['count','Группы'],[null,'Действия']]);count('programs',rows.length,selected.length);
    document.getElementById('programs-body').innerHTML=rows.map(p=>`<tr class="group-data-row"><td><div class="program-name-cell">${SchoolPrograms.safeCover(p.cover_url)?`<img class="program-cover-thumb" loading="lazy" src="${esc(SchoolPrograms.safeCover(p.cover_url))}" alt="">`:''}<button class="group-name" data-catalog-action="program-view" data-id="${esc(p.id)}">${esc(p.title)}</button></div></td><td>${esc(SchoolPrograms.typeLabel(p.program_type))}<span class="group-subtitle">${esc((p.formats||[]).map(f=>f==='онлайн'?'Онлайн':'Очно').join(', '))}${p.hours?' · '+esc(p.hours)+' ак. ч.':''}</span></td><td>${badge(p.section||'Не указан','purple')}</td><td class="group-code">${esc(p.tag)}</td><td>${action(p.count+' · К группам →','program-groups',p.id)}</td><td><div class="group-row-actions">${ctx.senior?`${action('Настройки','program-edit',p.id)}<a class="group-action group-action-primary" href="editor.html?program=${encodeURIComponent(p.id)}&role=${ctx.role}">Шаблон ЛК →</a>`:'<span class="group-readonly">Только просмотр</span>'}</div></td></tr>`).join('')||empty(6);
  }
  function renderRequests(){
    const all=ctx.state.requests.filter(r=>r.group||ctx.state.reviewers.includes(ctx.role));
    const rows=sorted(all.filter(r=>[r.name,r.email,ctx.groups[r.group]?.name,ctx.groups[r.group]?.code].join(' ').toLocaleLowerCase('ru').includes(value('search').trim().toLocaleLowerCase('ru'))&&(value('filter')==='all'||r.status===value('filter'))&&(!value('request-owner')||(value('request-owner')==='unassigned'?!r.group:ctx.groups[r.group]?.id===value('request-owner')))&&(!value('request-scope')||ctx.canReview(r))),'requests');
    head('requests',[['name','Слушатель'],['status','Статус'],[null,'Группа'],[null,'Администратор'],[null,'Действия']]);count('requests',rows.length,all.length);
    document.getElementById('requests-body').innerHTML=rows.map(r=>`<tr class="group-data-row"><td><button class="group-name" data-catalog-action="details" data-id="${r.id}">${esc(r.name)}</button><span class="group-subtitle">${esc(r.email)}</span></td><td>${badge(ctx.labels[r.status],{pending:'amber',approved:'green',rejected:'rose'}[r.status])}</td><td>${r.group?`<button class="group-name" data-catalog-action="group-view" data-id="${esc(r.group)}">${esc(ctx.groups[r.group]?.name)}</button><span class="group-subtitle">${esc(ctx.groups[r.group]?.code)}</span>`:'Нужна помощь с группой'}</td><td>${esc(ctx.groups[r.group]?.owner||'Ответственные сотрудники')}${!ctx.canReview(r)?'<small class="group-readonly">Только просмотр</small>':''}</td><td><div class="group-row-actions">${r.status==='pending'&&ctx.canReview(r)?`${r.group?action('Открыть доступ','approve',r.id,true):''}${action('Назначить группу','assign',r.id)}${action('Отклонить','reject',r.id)}`:action('Подробнее','details',r.id)}${r.mail?action('Письмо','mail',r.id):''}</div></td></tr>`).join('')||empty(5);
  }
  const directory=StudentDirectory.create({groups:ctx.groups,sampleGroups:window.PortalDirectorySamples.groups,requests:ctx.state.requests,closes:ctx.closes});
  const date=v=>v?new Intl.DateTimeFormat('ru-RU',{timeZone:'Asia/Yekaterinburg',dateStyle:'short',timeStyle:'short'}).format(new Date(v)):'Ещё не посещал';
  const studentRows=directory.rows;
  const groupContext=document.getElementById('student-group-context');
  groupContext.addEventListener('click',e=>{if(e.target.closest('[data-participants-back]'))ctx.showGroups();});
  function selectGroup(id){
    ['student-search','student-status','student-visits'].forEach(key=>document.getElementById(key).value='');
    renderStudents();document.getElementById('student-group').value=id;renderStudents();
  }
  function renderStudents(){
    const select=document.getElementById('student-group'),selected=select.value;select.innerHTML='<option value="">Все группы</option>'+opts(Object.entries(ctx.groups).map(([id,g])=>[id,g.code]));select.value=selected;
    const groupId=select.value,group=ctx.groups[groupId];
    const all=studentRows().filter(p=>!groupId||p.groups.includes(groupId)).map(p=>({...p,status:directory.statusFor(p,groupId)})),rows=sorted(all.filter(p=>(p.name+' '+p.email).toLocaleLowerCase('ru').includes(value('student-search').trim().toLocaleLowerCase('ru'))&&(!value('student-group')||p.groups.includes(value('student-group')))&&(!value('student-status')||p.status===value('student-status'))&&(!value('student-visits')||(value('student-visits')==='never'?!p.last:!!p.last))),'students');
    groupContext.hidden=!group;
    groupContext.innerHTML=group?`<button class="text-button" data-participants-back>← К группам</button><h2 tabindex="-1">Участники группы «${esc(group.name)}»</h2><p>${esc(group.code)} · Всего участников: <strong>${all.length}</strong></p><p class="form-note">Зачисленные слушатели, включая тех, чей доступ завершён. Заявки на проверке сюда не входят.</p>`:'';
    if(new URLSearchParams(location.search).get('panel')==='students'){const url=new URL(location.href);if(groupId)url.searchParams.set('group',groupId);else url.searchParams.delete('group');history.replaceState(null,'',url);}
    head('students',[['name','Слушатель'],['status','Доступ'],[null,'Группы'],['last','Последний визит в ЛК'],[null,'Действия']]);count('students',rows.length,all.length);
    document.getElementById('students-body').innerHTML=rows.map(p=>`<tr class="group-data-row"><td><button class="group-name" data-catalog-action="student-view" data-id="${p.id}">${esc(p.name)}</button><span class="group-subtitle">${esc(p.email)}</span></td><td>${badge({active:'Открыт',closed:'Завершён',pending:'Не открыт'}[p.status],{active:'green',closed:'gray',pending:'amber'}[p.status])}</td><td>${p.groups.map(id=>`<button class="group-name" data-catalog-action="group-view" data-id="${esc(id)}">${esc(ctx.groups[id]?.code)}</button>`).join('<br>')||'<span class="group-unset">Нет доступа к группам</span>'}</td><td>${date(p.last)}</td><td>${action('История посещений →','student-view',p.id)}</td></tr>`).join('')||(group&&!all.length?'<tr><td class="group-empty" colspan="5"><strong>В группе пока нет участников</strong><p>Слушатель появится здесь после открытия доступа к группе.</p></td></tr>':empty(5));
  }
  function openStudent(id){
    const p=studentRows().find(p=>p.id===id);if(!p)return;
    dialog.innerHTML=`<button class="dialog-close" type="button" aria-label="Закрыть">×</button><h2 id="student-title">${esc(p.name)}</h2><p>${esc(p.email)}</p><h3>Группы и доступ</h3>${p.groups.length?p.groups.map(id=>`<p>${esc(ctx.groups[id]?.name)}<br><span class="group-subtitle">${esc(ctx.groups[id]?.code)} · доступ до ${esc(ctx.closes(ctx.groups[id]))}</span></p>`).join(''):'<p>Доступ к группам ещё не открыт.</p>'}<h3>История посещений в ЛК</h3><p class="form-note">Открытия страниц · Екатеринбург, UTC+5. История в демо вымышленная.</p>${p.visits.length?`<table class="group-grid"><thead><tr><th>Дата и время</th><th>Открытая страница</th></tr></thead><tbody>${p.visits.map(v=>`<tr><td>${date(v)}</td><td>Моё обучение</td></tr>`).join('')}</tbody></table>`:'<div class="empty">Посещений пока нет.</div>'}<div class="dialog-actions"><button class="btn outline" data-student-close>Закрыть</button></div>`;
    dialog.querySelectorAll('.dialog-close,[data-student-close]').forEach(b=>b.onclick=()=>dialog.close());dialog.showModal();
  }
  document.addEventListener('click',e=>{
    const b=e.target.closest('[data-catalog-action]');if(!b)return;const type=b.dataset.catalogAction,id=b.dataset.id;
    if(type==='student-view')openStudent(id);else if(type==='program-groups')ctx.openGroups('',id);else ctx.open(type,['details','approve','assign','reject','mail'].includes(type)?Number(id):id);
  });
  const renders={requests:renderRequests,programs:renderPrograms,students:renderStudents};
  document.addEventListener('click',e=>{const b=e.target.closest('[data-directory-sort]');if(!b)return;const kind=b.dataset.directorySort,s=sorts[kind],key=b.dataset.key;s.dir=s.key===key?-s.dir:1;s.key=key;renders[kind]();});
  ['request-owner','request-scope'].forEach(id=>document.getElementById(id).onchange=renderRequests);
  document.getElementById('program-presence').onchange=renderPrograms;
  ['program-source-filter','program-type-filter','program-format-filter'].forEach(id=>document.getElementById(id).onchange=renderPrograms);
  ['student-search','student-group','student-status','student-visits'].forEach(id=>document.getElementById(id).addEventListener(id==='student-search'?'input':'change',renderStudents));
  return {renderPrograms,renderRequests,renderStudents,selectGroup,participantCount:id=>directory.participants(id).length};
}};
