'use strict';
(() => {
  const key = 'vesh-portal-demo-v1';
  const initial = () => ({requests:[{id:1,name:'Илья Морозов',email:'waiting@vesh.test',group:'mba',status:'pending',message:'Учусь в ноябрьской группе. Договор оформлен.',mail:false},{id:2,name:'Наталья Соколова',email:'unknown@vesh.test',group:'',status:'pending',message:'Поступила на программу по управлению. Не помню номер группы.',mail:false},{id:3,name:'Олег Крылов',email:'other@vesh.test',group:'excel',status:'pending',message:'Корпоративная группа, онлайн.',mail:false}],reviewers:['admin','senior'],days:30,customGroups:{},programs:null,groupOverrides:{}});
  let state = initial();
  try { const saved = JSON.parse(localStorage.getItem(key)); if(saved && Array.isArray(saved.requests) && saved.requests.length===3 && Array.isArray(saved.reviewers)) state=saved; } catch {}
  window.addEventListener('storage',event=>{
    if(event.key!==key||!event.newValue)return;
    try{const saved=JSON.parse(event.newValue);if(saved.academicAdvisors){state.academicAdvisors=saved.academicAdvisors;document.dispatchEvent(new CustomEvent('academic-advisors-changed'));}}catch{}
  });
  const refreshAdvisors=()=>{try{const saved=JSON.parse(localStorage.getItem(key));if(saved?.academicAdvisors){state.academicAdvisors=saved.academicAdvisors;document.dispatchEvent(new CustomEvent('academic-advisors-changed'));}}catch{}};
  window.addEventListener('focus',refreshAdvisors);
  document.addEventListener('refresh-academic-advisors',refreshAdvisors);
  const save = () => { try { localStorage.setItem(key, JSON.stringify(state)); return true; } catch { notice('Изменение применено на этой странице. Браузер не разрешил сохранить его между страницами.'); return false; } };
  const notice = text => { const el=document.getElementById('demo-notice'); if(el) el.textContent=text; };
  const escape = value => String(value ?? '').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const sections = ['HR','Аналитика','КОРП','Маркетинг','Менеджмент','Продажи','Производство','Упр. навыки','Финансы','Центр','НЕДОСТУПНО'];
  const statuses = ['Тех. группа','Запускается','Идет обучение','На закрытии','Архив','обучение отложено','Экскурсия','Продление доступа'];
  const types = ['ИНД, Личный кабинет','Сборное ОЧНО','Сборное ОНЛАЙН','Гибридный формат','Корп. обучение','Тех. параметр','КУРС В ЗАПИСИ','Экскурсия'];
  const directory = window.PortalDirectorySamples;
  const names = Object.fromEntries(Object.entries(directory.staff).map(([id,p])=>[id,p.name]));
  state.programs = state.programs && typeof state.programs==='object' ? state.programs : {
    commercial:{title:'MBA Коммерческий директор',tag:'ДЕМО_КД',section:'Продажи'},
    excel:{title:'Excel для эффективной работы',tag:'ДЕМО_EXCEL',section:'Аналитика'},
    management:{title:'Профессиональные навыки управления людьми',tag:'ДЕМО_МЕН',section:'Упр. навыки'}
  };
  state.programs.spo??={title:'Специалист по оценке персонала',tag:'ДЕМО_СПО',section:'HR',template_key:'short'};
  state.programs.competencies??={title:'Оценка компетенций',tag:'ДЕМО_ОК',section:'HR',template_key:'short'};
  const programs = state.programs;
  for(const [id,p] of Object.entries(programs)){p.template_key??=(id==='excel'?'short':'mba');}
  for(const [id,p] of Object.entries(directory.programs)) programs[id]??=structuredClone(p);
  if(state.programCatalogVersion!==SchoolPrograms.version){SchoolPrograms.apply(state);save();}
  const groups = structuredClone(directory.groups);
  state.customGroups = state.customGroups && typeof state.customGroups==='object' ? state.customGroups : {};
  state.groupOverrides = state.groupOverrides && typeof state.groupOverrides==='object' ? state.groupOverrides : {};
  for(const group of Object.values(state.customGroups)){if(!group.program_id)group.code=String(group.code||'').replace(/ \u00b7 /g,' ');}
  if(state.directoryVersion!==directory.version){
    state.previousGroupOverrides=structuredClone(state.groupOverrides);
    for(const id of Object.keys(directory.groups)){
      const previous=state.groupOverrides[id];
      if(previous){const content=Object.fromEntries(['page_blocks','page_template','schedule','materials'].filter(k=>previous[k]!==undefined).map(k=>[k,previous[k]]));state.groupOverrides[id]={...structuredClone(directory.groups[id]),...content};}
    }
    state.directoryVersion=directory.version;save();
  }
  Object.assign(groups,state.customGroups,state.groupOverrides);
  if(window.SchoolSchedules&&state.schoolScheduleVersion!==SchoolSchedules.version){
    state.previousSchoolSchedules??={};
    for(const [id,source] of Object.entries(SchoolSchedules.groups)){
      const group=groups[id];if(!group)continue;
      state.previousSchoolSchedules[id]=structuredClone(group.page_blocks||group.schedule||[]);
      const blocks=CoursePages.getBlocks(group);let schedule=blocks.find(b=>b.type==='schedule');
      if(!schedule){schedule=CoursePages.template('short').find(b=>b.type==='schedule');blocks.push(schedule);}
      schedule.data.lessons=structuredClone(source.lessons);schedule.data.source_url='';
      if(source.pending_lessons.length)blocks.push({type:'info',data:{block_id:'school-undated',title:'Занятия без даты',visible:true,text:'Дата и время будут уточнены администратором.\n\n'+source.pending_lessons.map(l=>l.title+' — '+l.teacher+' ('+l.hours+' ак. ч.)').join('\n')}});
      group.page_blocks=blocks;state.groupOverrides[id]={...group};
    }
    state.schoolScheduleVersion=SchoolSchedules.version;save();
  }
  if(window.RealCourseContent){
    if(RealCourseContent.apply(state,groups,directory))save();
    for(const [id,person] of Object.entries(directory.staff))names[id]=person.name;
  }
  const options = (values,selected='') => values.map(value=>`<option value="${escape(value)}"${value===selected?' selected':''}>${escape(value)}</option>`).join('');
  const programOptions = selected => Object.entries(programs).map(([id,p])=>`<option value="${escape(id)}"${id===selected?' selected':''}>${escape(p.tag)} — ${escape(p.title)}</option>`).join('');
  const safeUrl = value => { try { const url=new URL(value);return ['http:','https:'].includes(url.protocol)?url.href:''; } catch { return ''; } };
  const closes = (g,extension=true) => {
    if(!g.graduates_at)return '';
    const date=new Date(g.graduates_at+'T12:00:00Z');date.setUTCDate(date.getUTCDate()+Number(g.access_days??30));
    const base=date.toISOString().slice(0,10);return extension && g.extended_until>base?g.extended_until:base;
  };
  const canEditGroup = id => Boolean(groups[id] && (senior || groups[id].id===role));
  const groupLink = (id,waiting=false) => (id==='excel'?'excel.html':'course.html') + '?group='+encodeURIComponent(id)+(waiting?'&student=waiting':'');
  const dateLabel = value => value ? value.split('-').reverse().join('.') : 'Не указана';
  const senior = new URLSearchParams(location.search).get('role')==='senior';
  const role = Object.keys(directory.staff).includes(new URLSearchParams(location.search).get('role'))?new URLSearchParams(location.search).get('role'):'admin';
  const canReview = row => row.group ? senior || groups[row.group]?.id===role : state.reviewers.includes(role);
  const labels={pending:'На проверке',approved:'Доступ открыт',rejected:'Отклонена'};
  const badges={pending:'wait',approved:'',rejected:'rejected'};
  if(location.pathname.endsWith('courses.html')){
    const student=StudentDirectory.create({groups,sampleGroups:directory.groups,requests:state.requests,closes}).rows().find(person=>person.id==='student-0');
    const today=new Intl.DateTimeFormat('sv-SE',{timeZone:'Asia/Yekaterinburg'}).format(new Date());
    const cards=document.querySelector('.cards');
    cards.innerHTML=(student?.groups||[]).map(id=>{
      const g=groups[id],available=closes(g)>=today,finished=g.graduates_at<today;
      const status=!available?'Доступ завершён':finished?'Обучение завершено':g.starts_at>today?'Доступ открыт':'Идёт обучение';
      return `<article class="card course-tile" data-learning-group="${escape(id)}"><div class="tile-cover course-hero"><div class="eyebrow">${escape(g.code)} — ${escape(g.format)}</div><h2>${escape(g.name)}</h2></div><div class="tile-bottom"><div><span class="badge ${!available||finished?'closed':''}">${status}</span><p class="learning-dates">Начало обучения ${dateLabel(g.starts_at)}</p>${available?`<p class="learning-access">Материалы доступны до ${dateLabel(closes(g))}</p>`:''}</div>${available?`<a class="btn" href="${groupLink(id)}">Перейти ↗</a>`:''}</div></article>`;
    }).join('');
    cards.querySelectorAll('[data-learning-group]').forEach(card=>{
      const g=groups[card.dataset.learningGroup],program=programs[g.program_id];
      GroupHero.render(card.querySelector('.tile-cover'),{...g,program_cover_url:SchoolPrograms.coverFor(program),program_type:program?.program_type});
    });
  }
  document.querySelector('[data-reset]')?.addEventListener('click',()=>{state=initial();save();notice('Демонстрация сброшена. Все три заявки снова ожидают проверки.');});
  document.querySelectorAll('[data-demo-form]').forEach(form=>{
    form.querySelectorAll('textarea').forEach(el=>{el.value='';el.readOnly=false;});
    const select=form.querySelector('select'); if(select){ select.innerHTML='<option value="">Не знаю свою группу / нужна помощь</option>'+Object.entries(groups).filter(([,g])=>g.registration_open!==false).map(([id,g])=>`<option value="${escape(id)}">${escape(g.name)} — старт ${dateLabel(g.starts_at)}</option>`).join(''); if(form.dataset.demoForm==='register.html')select.value='mba'; }
    form.addEventListener('submit',event=>{event.preventDefault(); const id=form.querySelector('select')?.value;const row=state.requests[0];row.group=groups[id]?id:'';row.message=form.querySelector('textarea')?.value||'';row.status='pending';row.mail=false;save();location.href='waiting.html';});
  });
  if(location.pathname.endsWith('waiting.html')){
    const row=state.requests[0];
    document.querySelectorAll('header a[href="courses.html"]').forEach(a=>a.href='waiting.html');
    const box=document.querySelector('.waiting-card');
    if(row.status==='approved') box.innerHTML=`<div><span class="badge">Доступ открыт</span><h2>Можно начинать обучение</h2><p>Администратор одобрил вашу демонстрационную заявку. Материалы группы доступны.</p><a class="btn" style="margin-top:20px" href="${groupLink(row.group,true)}">Перейти к обучению ↗</a></div><div><h3>${escape(groups[row.group]?.name)}</h3><p style="margin-top:12px">${row.mail?'Подготовлен пример письма об открытии доступа.':'Доступ открыт без письма.'} Реальные сообщения не отправлялись.</p></div>`;
    if(row.status==='rejected') box.innerHTML='<div><span class="badge rejected">Заявка отклонена</span><h2>Уточним вашу группу</h2><p>В демонстрации администратор отклонил заявку. Вы можете выбрать другую группу и подать её повторно.</p><a class="btn" style="margin-top:20px" href="register.html">Повторить заявку ↗</a></div>';
    const request=document.querySelector('.request-row');
    if(request){ request.querySelector('h3').textContent=groups[row.group]?.name || 'Помощь с выбором группы';request.querySelector('small').textContent=groups[row.group]?.code || 'Обращение передано ответственным сотрудникам';const badge=request.querySelector('.badge');badge.className='badge '+badges[row.status];badge.textContent=labels[row.status]; }
  }
  if(new URLSearchParams(location.search).get('student')==='waiting'){ document.querySelectorAll('.person .name').forEach(el=>el.textContent='Илья Морозов'); document.querySelectorAll('.person .avatar').forEach(el=>el.textContent='И'); document.querySelectorAll('a[href="courses.html"]').forEach(el=>el.href='waiting.html'); }
  const customId=new URLSearchParams(location.search).get('group');
  const pageGroup=groups[customId || (location.pathname.endsWith('excel.html')?'excel':'mba')];
  if(pageGroup&&(location.pathname.endsWith('course.html')||location.pathname.endsWith('excel.html'))){
    document.title=pageGroup.name+' — ВЭШ';
    document.querySelector('.hero-layout h1').textContent=pageGroup.name;
    document.querySelector('.breadcrumb span:last-child').textContent=pageGroup.code;
    document.querySelector('.course-hero .eyebrow').textContent='Пространство обучения — '+pageGroup.code;
    document.querySelector('.hero-meta').innerHTML=`<div>Формат<strong>${escape(pageGroup.format)}</strong></div><div>Начало обучения<strong>${dateLabel(pageGroup.starts_at)}</strong></div>`;
    if(pageGroup.description)document.querySelector('.hero-side p').textContent=pageGroup.description;
    const contact=document.querySelector('.contact');if(contact){contact.querySelector('h3').textContent=pageGroup.owner;contact.querySelector('.avatar').textContent=pageGroup.owner.slice(0,1);}
  }
  if((location.pathname.endsWith('course.html')||location.pathname.endsWith('excel.html'))&&state.customGroups[customId]){
    const g=state.customGroups[customId];
    document.title=g.name+' — ВЭШ';
    document.querySelector('.course-hero-inner').innerHTML=`<div class="breadcrumb"><a href="admin.html?role=senior&panel=groups">Группы</a><span>/</span><span>${escape(g.code)}</span></div><div class="hero-layout"><div><div class="eyebrow">Пространство обучения — ${escape(g.code)}</div><h1>${escape(g.name)}</h1><div class="hero-meta"><div>Формат<strong>${escape(g.format)}</strong></div><div>Начало обучения<strong>${dateLabel(g.starts_at)}</strong></div></div></div><div class="hero-side"><span class="badge">Предпросмотр группы</span><p>${escape(g.description || 'Всё для обучения вашей группы — в одном месте.')}</p></div></div>`;
    document.querySelector('main.shell').innerHTML=`<section class="section" id="overview"><div class="overview"><div class="card next-class course-card-empty"><div class="eyebrow">Новая учебная группа</div><h2>Расписание скоро появится</h2><p>Даты, занятия и материалы этой группы будут опубликованы отдельно.</p></div><div class="card"><div class="eyebrow muted">На связи с вами</div><h3 style="margin-top:20px">${escape(g.owner)}</h3><p class="muted">Администратор группы</p></div></div></section><section class="section" id="schedule"><div class="section-title"><h2>Расписание группы</h2></div><div class="empty">Расписание ещё не опубликовано.</div></section><section class="section" id="recordings"><div class="section-title"><h2>Записи занятий</h2></div><div class="empty">Записи появятся после занятий.</div></section><section class="section" id="materials"><div class="section-title"><h2>Материалы для обучения</h2></div><div class="empty">У этой группы пока нет материалов.</div></section><section class="section" id="help"><div class="section-title"><h2>Помощь с обучением</h2></div><div class="card"><p>Администратор — ${escape(g.owner)}.</p><p class="muted">Доступ действует до ${dateLabel(closes(g))} включительно.</p></div></section>`;
  }
  if(pageGroup&&(location.pathname.endsWith('course.html')||location.pathname.endsWith('excel.html'))){
    const help=document.querySelector('#help');
    if(help){
      const paragraphs=help.querySelectorAll('details p');
      if(paragraphs.length){paragraphs[0].textContent='Ваш администратор — '+pageGroup.owner+'.';paragraphs[paragraphs.length-1].textContent='Общий срок группы — до '+dateLabel(closes(pageGroup))+' включительно.';}
      const chat=safeUrl(pageGroup.chat_url);
      if(chat){const info=document.createElement('p');info.className='form-note';info.innerHTML=`<a class="text-link" href="${escape(chat)}" target="_blank" rel="noopener">Открыть чат группы ↗</a>`;help.append(info);}
    }
  }
    if(!groups.mba.page_blocks && !groups.mba.schedule)groups.mba.schedule=[{date:'2026-11-06',time:'10:00–18:00',title:'Бизнес и организация как система',teacher:'Алексей Миронов',module:'Модуль 1 — Стратегия и управление'},{date:'2026-11-07',time:'10:00–18:00',title:'Модель устойчивой организации',teacher:'Алексей Миронов',module:'Модуль 1 — Стратегия и управление'},{date:'2026-12-04',time:'17:00–21:00',title:'Команда и управленческое взаимодействие',teacher:'Ирина Лебедева',module:'Модуль 2 — Управление людьми'}];
  const pageContext={state,programs,groups,names,staff:directory.staff,senior,role,canEditGroup,save,groupLink,closes,groupId:customId||(location.pathname.endsWith('excel.html')?'excel':'mba')};
  const advisorDirectory=window.AcademicAdvisors?.init(pageContext);
  const staffDirectory=AdminTools.init(pageContext);
  if(location.pathname.endsWith('editor.html')){CoursePages.initEditor(pageContext);return;}
  if(pageGroup&&(location.pathname.endsWith('course.html')||location.pathname.endsWith('excel.html'))){

    CoursePages.render(pageContext,pageGroup);
  }
  if(!document.getElementById('requests-body')) return;
  if(senior){ document.getElementById('create-course').hidden=false;document.getElementById('create-course-help').hidden=true;document.getElementById('create-program').hidden=false; }
  const dialog=document.getElementById('action-dialog');let action=null;let active=null;let selectedId=null;
  const close=()=>dialog.close();
  dialog.querySelectorAll('[data-close],.dialog-close').forEach(b=>b.addEventListener('click',close));
  const groupTable = window.AdminGroups.init({...pageContext, names, statuses, escape, safeUrl, dateLabel, countParticipants:id=>catalogs.participantCount(id), openParticipants:id=>navigateStudents(id), open:(type,id)=>open(type,id)});
  const schedules=AdminSchedules.init({...pageContext,escape});
  const catalogs=AdminCatalog.init({...pageContext,escape,safeUrl,canReview,labels,open:(type,id)=>open(type,id),openGroups:(owner,program)=>navigateGroups(owner,program),showGroups:()=>showPanel('groups')});
  const renderCatalog=()=>{
    const groupFilter=document.getElementById('group-program-filter'),chosen=groupFilter.value;
    groupFilter.innerHTML='<option value="">Все программы</option>'+programOptions(chosen);
    groupTable.render();
    catalogs.renderPrograms();
    staffDirectory?.render();
    advisorDirectory?.render();
    schedules.render();
  };
  const openProgram=(type,id,body,title,confirm)=>{
    const p=programs[id]||{title:'',tag:'',section:''};
    if(type==='program-view'){
      title.textContent=p.title;
      const linked=Object.entries(groups).filter(([,g])=>g.program_id===id);
      const details=[['ТЕГ программы',p.tag],['Раздел',p.section],['Тип программы',p.program_type],['Формат',(p.formats||[]).map(f=>f==='онлайн'?'Онлайн':'Очно').join(', ')],['Академические часы',p.hours],['Документ',p.certificate]];
      body.innerHTML=`${SchoolPrograms.coverFor(p)?`<img class="program-cover" src="${escape(SchoolPrograms.coverFor(p))}" alt="Обложка программы ${escape(p.title)}">`:''}<dl class="dialog-fields">${details.map(([label,v])=>`<div><dt>${label}</dt><dd>${escape(v??'Не указано')||'Не указано'}</dd></div>`).join('')}</dl>${safeUrl(p.website_url)?`<p><a class="text-link" href="${escape(safeUrl(p.website_url))}" target="_blank" rel="noopener">Программа на сайте школы ↗</a></p>`:''}<h3>Группы программы</h3>${linked.length?linked.map(([gid,g])=>`<p><a class="text-link" href="${groupLink(gid)}">${escape(g.code)} — ${escape(g.name)} ↗</a></p>`).join(''):'<p>У этой программы пока нет групп.</p>'}`;confirm.hidden=true;return;
    }
    title.textContent=type==='program-create'?'Добавить программу':'Редактировать программу';
    body.innerHTML=`<label class="field">Название программы<input name="program_title" required maxlength="180" value="${escape(p.title)}"></label><label class="field">ТЕГ программы<input name="program_tag" required maxlength="60" value="${escape(p.tag)}" autocapitalize="off" spellcheck="false"></label><p class="form-note">Обязательный уникальный ТЕГ по регламенту школы. Введите его точно, сохранив регистр и символы.</p><label class="field">Раздел<select name="program_section"><option value="">Не указан</option>${options(sections,p.section)}</select></label><label class="field">Базовый шаблон ЛК<select name="program_template">${Object.entries(CoursePages.templates).map(([k,v])=>`<option value="${k}"${(p.template_key||'mba')===k?' selected':''}>${v}</option>`).join('')}</select></label><p class="form-note">Смена шаблона заменит базовые блоки программы для следующих групп. Уже созданные группы сохранят своё наполнение.</p><p class="form-note">Изменение программы не заменяет названия, расписание и материалы уже созданных групп.</p>`;
    confirm.textContent=type==='program-create'?'Создать программу':'Сохранить программу';
  };
  const invalid=(form,name,message)=>{const input=form.elements.namedItem(name);input.setCustomValidity(message);input.reportValidity();input.addEventListener('input',()=>input.setCustomValidity(''),{once:true});};
  const trimmedRequired=(form,names)=>{for(const name of names){if(!form.elements.namedItem(name).value.trim()){invalid(form,name,'Заполните это поле.');return false;}}return true;};
  const saveProgram=form=>{
    if(!senior||!trimmedRequired(form,['program_title','program_tag']))return;
    const data=new FormData(form),tag=String(data.get('program_tag')).trim();
    if(Object.entries(programs).some(([id,p])=>id!==selectedId&&p.tag===tag)){invalid(form,'program_tag','Программа с таким ТЕГом уже есть.');return;}
    const id=selectedId||'program-'+crypto.randomUUID();
    programs[id]={...programs[id],page_blocks:programs[id]?.template_key===String(data.get('program_template'))?programs[id]?.page_blocks:null,template_key:String(data.get('program_template')),title:String(data.get('program_title')).trim(),tag,section:String(data.get('program_section'))};save();close();if(action==='program-create')document.getElementById('program-source-filter').value='';renderCatalog();showPanel('programs');notice('Программа сохранена. Её можно выбрать при создании группы.');
  };
  const openGroup=(type,id,body,title,confirm)=>{
    const g=groups[id]||{name:'',code:'',program_id:'',id:'admin',format:'Очно',access_days:30,status:'Запускается',registration_open:true};
    title.textContent=type==='create'?'Добавить группу':'Редактировать группу';
    const input=(label,name,value,extra='')=>`<label class="field">${label}<input name="${name}" value="${escape(value)}" ${extra}></label>`;
    body.innerHTML=`<p>Выберите программу. У группы будут свои слушатели, расписание, материалы и записи.</p><div class="form-grid"><label class="field full">Программа из справочника<select name="group_program" required><option value="">Выберите программу</option>${programOptions(g.program_id)}</select></label><div class="form-note full" id="group-section">Раздел: ${escape(programs[g.program_id]?.section||'не указан')}</div><label class="field full">Название программы для группы<input name="group_title" required maxlength="180" value="${escape(g.name)}" placeholder="Название программы и номер группы"></label>${input('№ группы','group_number',g.number,'required maxlength="60"')}${input('ТЕГ группы — автоматически','group_code',g.code,'readonly')}<p class="form-note full">ТЕГ программы + дефис + номер группы. Номер может содержать буквы, дробь и другие обозначения школы.</p><label class="field">Статус группы<select name="group_status" required>${options(statuses,g.status||'Запускается')}</select></label><label class="field">Тип группы<select name="group_type"><option value="">Не указан</option>${options(types,g.group_type)}</select></label><label class="field">Администратор<select name="group_owner" required ${!senior?'disabled':''}>${Object.entries(names).map(([id,name])=>`<option value="${id}"${id===g.id?' selected':''}>${name}</option>`).join('')}</select></label><label class="field">Формат обучения<select name="group_format">${options(['Очно','Онлайн','Смешанный'],g.format)}</select></label><label class="field full">Чат группы<input name="group_chat" type="url" maxlength="2000" placeholder="https://" value="${escape(g.chat_url)}"></label><h3 class="form-section-title">Даты и доступ</h3>${input('Дата запуска','group_start',g.starts_at,'type="date" required')}${input('Дата выпуска','group_end',g.graduates_at,'type="date" required')}${input('Доступ после выпуска, дней','group_days',g.access_days??30,'type="number" min="0" max="3650" required')}${input('До какого числа продлён доступ','group_extension',g.extended_until,'type="date"')}<p class="form-note full" id="group-close-date"></p><p class="form-note full">Статус нужен для учёта. Срок доступа рассчитывается по датам. Продление не сокращает основной срок.</p><label class="check full"><input name="group_open" type="checkbox" ${g.registration_open?'checked':''}>Приём заявок открыт</label><h3 class="form-section-title">Дополнительно</h3><label class="field full">Приветствие для слушателей<textarea name="group_description" rows="2" maxlength="3000">${escape(g.description)}</textarea></label><label class="field full">Комментарий администратора<textarea name="group_notes" rows="2" maxlength="3000">${escape(g.admin_notes)}</textarea></label><p class="form-note full">Комментарий виден только сотрудникам.</p><label class="check full"><input name="group_completed" type="checkbox" ${g.setup_completed?'checked':''}>Заполнение группы завершено</label></div><p class="form-note" style="margin-top:18px">Демонстрационные изменения сохраняются только в этом браузере.</p>`;
    confirm.textContent=type==='create'?'Создать группу':'Сохранить группу';
    body.querySelector('[name=group_program]').addEventListener('change',event=>{const p=programs[event.target.value],field=body.querySelector('[name=group_title]');if(!field.value.trim()&&p)field.value=p.title;document.getElementById('group-section').textContent='Раздел: '+(p?.section||'не указан');});
    const updateCode=()=>{body.querySelector('[name=group_code]').value=(programs[body.querySelector('[name=group_program]').value]?.tag||'')+'-'+body.querySelector('[name=group_number]').value.trim();};
    ['group_program','group_number'].forEach(name=>body.querySelector('[name='+name+']').addEventListener('input',updateCode));
    const updateDate=()=>{const end=body.querySelector('[name=group_end]');end.min=body.querySelector('[name=group_start]').value;body.querySelector('[name=group_extension]').min=end.value;const value={graduates_at:end.value,access_days:Number(body.querySelector('[name=group_days]').value),extended_until:body.querySelector('[name=group_extension]').value};document.getElementById('group-close-date').textContent=value.graduates_at?'Дата архивации: '+dateLabel(closes(value,false))+'. Доступ до '+dateLabel(closes(value))+' включительно.':'Дата закрытия появится после заполнения даты выпуска.';};
    ['group_start','group_end','group_days','group_extension'].forEach(name=>body.querySelector('[name='+name+']').addEventListener('input',updateDate));updateDate();
  };
  const saveGroup=form=>{
    if(action==='create'?!senior:!canEditGroup(selectedId))return;
    if(!trimmedRequired(form,['group_title','group_number']))return;
    const data=new FormData(form),code=selectedId && groups[selectedId].program_id===String(data.get('group_program')) && groups[selectedId].number===String(data.get('group_number')).trim() ? groups[selectedId].code : (programs[String(data.get('group_program'))]?.tag||'')+'-'+String(data.get('group_number')).trim(),program=String(data.get('group_program')),start=String(data.get('group_start')),end=String(data.get('group_end')),chat=String(data.get('group_chat')).trim(),extension=String(data.get('group_extension'));
    if(!programs[program]){invalid(form,'group_program','Выберите программу из справочника.');return;}
    if(Object.entries(groups).some(([id,g])=>id!==selectedId&&g.code===code)){invalid(form,'group_number','Группа с таким ТЕГом уже есть.');return;}
    if(end<start){invalid(form,'group_end','Дата выпуска должна быть не раньше даты запуска.');return;}
    if(extension&&extension<end){invalid(form,'group_extension','Продление должно быть не раньше даты выпуска.');return;}
    if(chat&&!safeUrl(chat)){invalid(form,'group_chat','Укажите ссылку, начинающуюся с https:// или http://.');return;}
    const id=selectedId||'group-'+crypto.randomUUID(),owner=senior?String(data.get('group_owner')):groups[selectedId].id;
    const g={...groups[id],name:String(data.get('group_title')).trim(),code,number:String(data.get('group_number')).trim(),program_id:program,id:owner,owner:names[owner],format:String(data.get('group_format')),group_type:String(data.get('group_type')),status:String(data.get('group_status')),chat_url:chat,starts_at:start,graduates_at:end,access_days:Number(data.get('group_days')),extended_until:extension,registration_open:data.has('group_open'),description:String(data.get('group_description')).trim(),admin_notes:String(data.get('group_notes')).trim(),setup_completed:data.has('group_completed')};
    if(!selectedId){g.page_template=programs[program].template_key||'mba';g.page_blocks=JSON.parse(JSON.stringify(programs[program].page_blocks??CoursePages.template(g.page_template)));}
    groups[id]=g;if(['mba','excel'].includes(id)){state.groupOverrides[id]=g;}else{state.customGroups[id]=g;}save();close();groupTable.resetFilters();render();renderCatalog();showPanel('groups');notice('Группа «'+g.name+'» сохранена. Открыть её страницу можно кнопкой «Зайти в ЛК».');
  };
  const viewGroup=(id,body,title)=>{
    const g=groups[id],p=programs[g.program_id];title.textContent=g.name;
    const entries=[['Программа',p?.title||'Нужно выбрать'],['ТЕГ программы',p?.tag||'Не указан'],['Раздел',p?.section||'Не указан'],['ТЕГ группы',g.code],['№ группы',g.number||'Не указан'],['Статус группы',g.status||'Не указан'],['Тип группы',g.group_type||'Не указан'],['Администратор',g.owner],['Дата запуска',dateLabel(g.starts_at)],['Дата выпуска',dateLabel(g.graduates_at)],['Доступ после выпуска, дней',g.access_days??30],['Дата архивации',dateLabel(closes(g,false))],['Продление до',g.extended_until?dateLabel(g.extended_until):'Без продления'],['Доступ до включительно',dateLabel(closes(g))],['Приём заявок',g.registration_open?'Открыт':'Закрыт'],['Заполнение завершено',g.setup_completed?'Да':'Нет']];
    body.innerHTML=`<p>${canEditGroup(id)?'Доступно редактирование':'Только просмотр'}</p><p><button type="button" class="group-action group-participants" data-participants-open="${escape(id)}">Участники: <strong>${catalogs.participantCount(id)}</strong> Открыть список →</button></p><dl class="dialog-fields">${entries.map(([name,value])=>`<div><dt>${name}</dt><dd>${escape(value)}</dd></div>`).join('')}<div class="full"><dt>Чат группы</dt><dd>${safeUrl(g.chat_url)?`<a class="text-link" href="${escape(safeUrl(g.chat_url))}" target="_blank" rel="noopener">Открыть чат ↗</a>`:'Не указан'}</dd></div><div class="full"><dt>Комментарий администратора</dt><dd>${escape(g.admin_notes||'Нет комментария')}</dd></div></dl>`;
    dialog.querySelector('.dialog-actions').insertAdjacentHTML('afterbegin',`<a data-group-portal-action class="btn outline" href="${groupLink(id)}&preview=1&role=${role}" target="_blank" rel="noopener">Открыть ЛК ↗</a> ${canEditGroup(id)?`<a data-group-portal-action class="btn" href="editor.html?group=${encodeURIComponent(id)}&role=${role}">Редактировать ЛК</a>`:''}`);
  };
  const render=()=>{
    document.getElementById('pending-count').textContent=state.requests.filter(r=>r.status==='pending').length;
    catalogs.renderRequests();
    catalogs.renderStudents();
    renderCatalog();
  };

  const open=(type,id)=>{
    if(['create','program-create','program-edit'].includes(type)&&!senior)return;
    if(type==='group-edit'&&!canEditGroup(id))return;
    active=state.requests.find(r=>r.id===id);action=type;selectedId=id;
    dialog.classList.toggle('course-dialog',['create','group-edit','group-view','program-view'].includes(type));
    dialog.querySelectorAll('[data-group-portal-action]').forEach(link=>link.remove());
    dialog.querySelector('[data-close]').textContent=type==='group-view'?'Закрыть':'Отмена';
    const body=document.getElementById('dialog-content');const title=document.getElementById('dialog-title');const confirm=document.getElementById('dialog-confirm');confirm.hidden=false;
    if(type.startsWith('program-')){openProgram(type,id,body,title,confirm);dialog.showModal();return;}
    if(type==='create'||type==='group-edit'){openGroup(type,id,body,title,confirm);dialog.showModal();return;}
    if(type==='group-view'){viewGroup(id,body,title);confirm.hidden=true;dialog.showModal();return;}
    const name=escape(active?.name);
    if(type==='approve'){title.textContent='Открыть доступ к группе';body.innerHTML=`<p>${name} — ${escape(groups[active.group].name)}</p><label class="check"><input id="send-email" type="checkbox" checked>Подготовить письмо «Доступ открыт»</label><p class="form-note" style="margin-top:15px">Если снять галочку, доступ откроется без письма. Здесь сохраняется только пример, отправки нет.</p>`;confirm.textContent='Открыть доступ';}
    if(type==='assign'){title.textContent='Назначить учебную группу';body.innerHTML=`<p>${name}. После назначения заявку проверит администратор выбранной группы.</p><label class="field">Группа<select id="target-group">${Object.entries(groups).map(([k,g])=>`<option value="${k}"${k===active.group?' selected':''}>${escape(g.name)}</option>`).join('')}</select></label>`;confirm.textContent='Назначить группу';}
    if(type==='reject'){title.textContent='Отклонить заявку';body.innerHTML=`<p>${name}. Материалы группы останутся недоступны. В демонстрации можно подать заявку повторно.</p><label class="field">Причина отклонения<textarea id="reject-reason" required></textarea></label>`;confirm.textContent='Отклонить заявку';}
    if(type==='details'){title.textContent='Заявка на обучение';const group=groups[active.group];const requestAction=(label,type)=>`<button type="button" class="btn ${type==='approve'?'':'outline'}" data-catalog-action="${type}" data-id="${active.id}">${label}</button>`;body.innerHTML=`<h3>${name}</h3><p>${escape(active.email)}</p><dl class="application-meta"><div><dt>Группа</dt><dd>${escape(group?.name||'Не выбрана')}${group?`<br>${escape(group.code)}`:''}</dd></div><div><dt>Администратор</dt><dd>${escape(group?.owner||'Ответственные сотрудники')}</dd></div><div><dt>Статус</dt><dd>${labels[active.status]}</dd></div></dl><p>${escape(active.message||'Комментарий не оставлен')}</p>${active.decisionNote?`<p>Причина отклонения: ${escape(active.decisionNote)}</p>`:''}${active.status==='pending'?`<p class="form-note">${canReview(active)?(group?'Проверьте группу перед открытием доступа. При необходимости её можно изменить.':'Сначала назначьте группу. После этого можно открыть доступ.'):'Заявку может обработать администратор группы или старший администратор.'}</p>`:''}<div class="application-actions">${active.status==='pending'&&canReview(active)?`${group?requestAction('Открыть доступ','approve'):''}${requestAction(group?'Изменить группу':'Назначить группу','assign')}${requestAction('Отклонить','reject')}`:''}${active.mail?requestAction('Письмо','mail'):''}</div>`;confirm.hidden=true;}
    if(type==='mail'){title.textContent='Предпросмотр письма';body.innerHTML=`<p>Пример для ${escape(active.email)} — без отправки</p><div class="mail-preview"><h3>Доступ к обучению открыт</h3><p>${name}, здравствуйте!</p><p>В кабинете доступна программа «${escape(groups[active.group].name)}».</p><a class="btn" href="${groupLink(active.group,true)}">Перейти в кабинет ↗</a></div>`;confirm.hidden=true;}
    if(type==='days'){title.textContent='Срок доступа после выпуска';body.innerHTML=`<label class="field">Количество дней<input id="access-days" type="number" min="0" max="3650" required value="${state.days}"></label><p class="form-note">Общее правило для всей демонстрационной группы.</p>`;confirm.textContent='Сохранить срок';}
    dialog.showModal();
  };
  document.getElementById('action-form').addEventListener('submit',event=>{
    event.preventDefault();
    if(action==='program-create'||action==='program-edit'){saveProgram(event.currentTarget);return;}
    if(action==='create'||action==='group-edit'){saveGroup(event.currentTarget);return;}
    if(active&&['approve','assign','reject'].includes(action)&&(!canReview(active)||active.status!=='pending')) return;
    if(action==='approve'){active.status='approved';active.mail=document.getElementById('send-email').checked;notice('Демонстрационный доступ открыт'+(active.mail?'. Пример письма подготовлен.': ' без письма.')+' Посмотрите кабинет слушателя.');}
    if(action==='assign'){active.group=document.getElementById('target-group').value;notice('Группа назначена. Доступ откроется после одобрения ответственным администратором.');}
    if(action==='reject'){active.decisionNote=document.getElementById('reject-reason').value.trim();if(!active.decisionNote)return;active.status='rejected';notice('Демонстрационная заявка отклонена.');}
    if(action==='days'){state.days=Number(document.getElementById('access-days').value);notice('Срок демонстрационной группы обновлён.');}
    save();close();render();
  });
  document.getElementById('search').addEventListener('input',render);document.getElementById('filter').addEventListener('change',render);
  const showPanel = panel => {
    if(panel==='reviewers')panel='profiles';
    const titles={requests:'Заявки на обучение',students:'Слушатели',groups:'Группы',programs:'Программы',profiles:'Администраторы',advisors:'Научные руководители',schedules:'Расписание'};
    if(!titles[panel])panel='requests';
    document.getElementById('create-course').hidden=!senior||panel!=='groups';
    const url=new URL(location.href);url.searchParams.set('panel',panel);const participantGroup=panel==='students'?document.getElementById('student-group').value:'';if(participantGroup)url.searchParams.set('group',participantGroup);else url.searchParams.delete('group');history.replaceState(null,'',url);
    document.querySelector('.page-heading h1').textContent=titles[panel];
    document.querySelector('.page-heading p').textContent={requests:'Проверка заявок и открытие доступа к обучению.',students:'Аккаунты слушателей, группы, доступ и история посещений кабинета.',groups:'Учебные группы со своими слушателями, датами и материалами.',programs:'Программы школы и связанные с ними группы.',profiles:'Аккаунт, контакты, группы и дополнительные права сотрудника.',advisors:'Общий справочник руководителей и контактов для связи.',schedules:'Расписание всех групп: просмотр опубликованных занятий и массовая правка таблицей.'}[panel];
    document.querySelectorAll('[data-panel]').forEach(x=>x.classList.toggle('active',x.dataset.panel===panel));
    Object.keys(titles).forEach(p=>document.getElementById(p+'-panel').hidden=p!==panel);
    staffDirectory?.render();
    advisorDirectory?.render();
    schedules.render();
  };
  const navigateStudents=id=>{close();catalogs.selectGroup(id);showPanel('students');document.querySelector('#student-group-context h2')?.focus();};
  dialog.addEventListener('click',event=>{const button=event.target.closest('[data-participants-open]');if(button)navigateStudents(button.dataset.participantsOpen);});
  const navigateGroups=(owner='',program='')=>{
    groupTable.resetFilters();document.getElementById('group-owner-filter').value=owner;
    document.getElementById('group-program-filter').value=program;renderCatalog();showPanel('groups');
  };
  pageContext.openGroups=navigateGroups;
  document.addEventListener('staff-profile-saved',render);
  document.querySelectorAll('[data-panel]').forEach(b=>b.addEventListener('click',()=>showPanel(b.dataset.panel)));
  document.getElementById('create-course').addEventListener('click',()=>open('create',null));
  document.getElementById('create-program').addEventListener('click',()=>open('program-create',null));
  document.getElementById('program-section-filter').innerHTML='<option value="">Все разделы</option>'+options(sections);
  ['program-search','group-search'].forEach(id=>document.getElementById(id).addEventListener('input',renderCatalog));
  ['program-section-filter','group-program-filter'].forEach(id=>document.getElementById(id).addEventListener('change',renderCatalog));
  const query=new URLSearchParams(location.search);
  render();
  showPanel(query.get('panel')||'requests');if(query.get('panel')==='students'&&groups[query.get('group')])navigateStudents(query.get('group'));if(query.get('owner')&&names[query.get('owner')])navigateGroups(query.get('owner'));if(senior&&query.get('create')==='1')open('create',null);
})();
