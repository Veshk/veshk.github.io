'use strict';
window.CoursePages = (() => {
  const labels = {contacts:'Контакты и чаты',schedule:'Расписание',join:'Подключиться к занятию',homework:'Домашние задания',recordings:'Записи занятий',materials:'Материалы для обучения',faq:'Вопросы об обучении',info:'Информационный блок',video:'Видео о школе',introductions:'Практика «Знакомство»',alias:'Общий блок из другого ЛК'};
  const templates = {mba:'MBA / длительная программа',short:'Короткая программа',corporate:'Корпоративная программа'};
  const clone = value => JSON.parse(JSON.stringify(value));
  const block = type => ({type,data:{title:labels[type],visible:true,block_id:crypto.randomUUID(),...(type==='contacts'?{chats:[]}:{}),...(type==='schedule'?{lessons:[],show_moscow:false,collapse_past:true}:{}),...(type==='recordings'?{videos:[]}:{}),...(type==='materials'?{text:'Все материалы вашей группы собраны в одной папке.'}:{})}});
  const template = key => {
    const list=['contacts','schedule','join','recordings','materials'].map(block);
    if(key==='mba')list.push({type:'info',data:{title:'Подготовка к курсовой работе',text:'Информация о консультациях и материалы появятся здесь.',visible:true}});
    return list;
  };
  const fallback = g => {
    const list=template(g.page_template||'short');
    list.find(b=>b.type==='schedule').data.lessons=clone(g.schedule||[]);
    if(g.chat_url)list[0].data.chats=[{label:'Чат группы',url:g.chat_url}];
    list.forEach((b,i)=>b.data.block_id='legacy-'+i);
    return list;
  };
  const getBlocks = g => clone(g.page_blocks??fallback(g)).map((b,i)=>({...b,data:{...b.data,block_id:b.data.block_id||'legacy-'+i}}));
  const resolveAlias=(b,groups)=>{const source=groups[b.data.source_group];const original=source&&getBlocks(source).find(x=>x.data.block_id===b.data.source_block&&x.type!=='alias'&&x.data.visible!==false);return original?{block:original,group:source}:null;};
  const usedBy=(gid,bid,groups)=>Object.entries(groups).filter(([,g])=>getBlocks(g).some(b=>b.type==='alias'&&b.data.source_group===gid&&b.data.source_block===bid));
  const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const url=value=>{try{const u=new URL(value);return ['http:','https:'].includes(u.protocol)?u.href:'';}catch{return '';}};
  const link=(value,label,cls='btn outline')=>url(value)?`<a class="${cls}" href="${esc(url(value))}" target="_blank" rel="noopener noreferrer">${esc(label)} ↗</a>`:'';
  const chatDescription='Присоединяйтесь к чатам, чтобы быть на связи с группой и получать объявления.';
  const contactDetails=(text,name)=>{
    const normalized=value=>String(value||'').trim().replace(/\s+/g,' ').toLocaleLowerCase('ru');
    const token=/[a-z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-z0-9-]+(?:\.[a-z0-9-]+)+|\+?\d[\d ()\u00a0.\-\u2010-\u2015]{7,}\d/gi;
    return String(text||'').split(/\r?\n/).map(line=>line.trim()).filter(line=>line&&normalized(line)!==normalized(name)).map(line=>{
      let linked=false;
      let last=0,html='';const clean=line.replace(/^(?:e-?mail|почта|тел(?:ефон)?\.?)\s*:\s*/i,'');
      for(const match of clean.matchAll(token)){
        html+=esc(clean.slice(last,match.index));const value=match[0],digits=value.replace(/\D/g,'');let href='';
        if(value.includes('@'))href='mailto:'+encodeURIComponent(value).replace(/%40/g,'@');
        else if(digits.length>=10&&digits.length<=15)href='tel:'+(digits.length===11&&digits.startsWith('8')?'+7'+digits.slice(1):(value.startsWith('+')?'+':'')+digits);
        html+=href?`<a href="${esc(href)}">${esc(value)}</a>`:esc(value);linked=linked||Boolean(href);last=match.index+value.length;
      }
      html+=esc(clean.slice(last));return `<p class="contact-line${linked?'':' contact-note'}">${html}</p>`;
    }).join('');
  };
  const dateLabel=value=>value?value.split('-').reverse().join('.'):'';
  const month=value=>{const d=new Date(value+'T12:00:00Z');return Number.isNaN(d.valueOf())?'Дата уточняется':new Intl.DateTimeFormat('ru',{month:'long',year:'numeric',timeZone:'UTC'}).format(d).replace(/\s*г\.$/,'');};
  const weekday=value=>{const d=new Date(value+'T12:00:00Z');return Number.isNaN(d.valueOf())?'':new Intl.DateTimeFormat('ru',{weekday:'short',timeZone:'UTC'}).format(d);};
  const moscow=value=>String(value||'').replace(/(\d{1,2})[:.](\d{2})/g,(_,h,m)=>`${String((Number(h)+22)%24).padStart(2,'0')}:${m}`);
  const today=()=>new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Yekaterinburg',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
  const recordingStatus=v=>v.status|| (url(v.url)?'published':'pending');
  const image=d=>url(d.image_url)?`<img class="block-image" src="${esc(url(d.image_url))}" alt="${esc(d.image_alt||'')}" loading="lazy" referrerpolicy="no-referrer">`:'';
  const schedule = (d,g) => {
    const rows=[...(d.lessons||[])].sort((a,b)=>(a.date||'').localeCompare(b.date||'')||(a.time||'').localeCompare(b.time||''));
    const months={};rows.forEach(row=>(months[(row.date||'').slice(0,7)]??=[]).push(row));
    const table=(key,lessons)=>`<div class="month-card"><div class="month-label">${esc(month(lessons[0].date))}</div><div class="schedule-scroll"><table class="compact-schedule"><colgroup><col class="col-date"><col class="col-time"><col class="col-lesson"><col class="col-teacher"></colgroup><thead><tr><th>Дата</th><th>Время${d.show_moscow?' ЕКБ / МСК':' ЕКБ'}</th><th>Занятие</th><th>Преподаватель</th></tr></thead><tbody>${lessons.map(l=>`<tr><td><strong>${esc(dateLabel(l.date))}</strong> <small>${esc(weekday(l.date))}</small></td><td class="lesson-time">${esc(l.time)}${d.show_moscow?`<small>${esc(moscow(l.time))} МСК</small>`:''}</td><td><strong>${esc(l.title)}</strong><div class="lesson-context">${l.module?`<span>${esc(l.module)}</span>`:''}<span>${esc(l.format||g.format)}</span></div>${link(l.url,'Подключиться','text-link')}</td><td>${esc(l.teacher)}</td></tr>`).join('')}</tbody></table></div></div>`;
    let current='',past='';Object.entries(months).forEach(([key,lessons])=>{const html=table(key,lessons);if(d.collapse_past&&lessons.every(l=>l.date&&l.date<today()))past+=html;else current+=html;});
    return `${past?`<details class="past-schedule"><summary>Прошедшие занятия</summary>${past}</details>`:''}${current}${rows.length?'':'<div class="empty">Расписание ещё не опубликовано.</div>'}${link(d.source_url,'Открыть исходное расписание')}`;
  };
  const content=(b,g)=>{
    const d=b.data;
    if(b.type==='contacts')return `<div class="contact-panel"><div class="contact-person">${AdminTools.avatar(g.staffProfile||{name:g.owner})}<div class="contact-details"><div class="eyebrow muted">Администратор группы</div><h3>${esc(g.owner)}</h3><div class="contact-info">${contactDetails(d.text||g.staffProfile?.text||'Поможет с расписанием и организационными вопросами.',g.owner)}</div>${link(d.contact_url||g.staffProfile?.contact_url,'Написать администратору','contact-message')}</div></div><div class="contact-chats"><h3>Чаты группы</h3>${(d.chat_text??chatDescription)?`<p class="chat-description">${esc(d.chat_text??chatDescription)}</p>`:''}<div class="chat-links">${(d.chats?.length?d.chats:g.chat_url?[{label:'Чат группы',url:g.chat_url}]:[]).map(c=>link(c.url,c.label)).join('')||'<p class="muted">Ссылки на чаты скоро появятся.</p>'}</div></div></div>`;
    if(b.type==='schedule')return schedule(d,g);
    if(b.type==='homework')return (d.tasks||[]).length?`<div class="homework-scroll"><table class="homework-table"><thead><tr><th>Статус</th><th>Срок</th><th>Модуль и задание</th><th>Материалы</th></tr></thead><tbody>${d.tasks.map(t=>`<tr><td><span class="content-status ${t.status==='past'?'is-past':'is-upcoming'}">${t.status==='past'?'Прошедшее':'Предстоит'}</span></td><td class="homework-deadline">${esc(dateLabel(t.deadline)||'Уточняется')}</td><td>${t.module?`<div class="homework-module">${esc(t.module)}</div>`:''}<p class="block-text">${esc(t.title)}</p></td><td>${link(t.url,t.button||'Открыть','text-link')}</td></tr>`).join('')}</tbody></table></div><p class="form-note">Статус общий для группы и не обозначает личное выполнение задания.</p>`:'<div class="empty">Задания появятся здесь после публикации.</div>';
    if(b.type==='faq')return (d.questions||[]).map(q=>`<details class="course-faq"><summary>${esc(q.question)}</summary><div class="faq-answer"><p class="block-text">${esc(q.answer)}</p>${link(q.url,q.button||'Подробнее','text-link')}</div></details>`).join('')||'<div class="empty">Ответы на вопросы скоро появятся.</div>';
    if(b.type==='recordings'){
      const modules={};(d.videos||[]).forEach(v=>(modules[v.module||'Записи занятий']??=[]).push(v));
      return Object.entries(modules).map(([name,items])=>`<div class="recording-module"><h3>${esc(name)}</h3><div class="recording-grid">${items.map(v=>{const ready=recordingStatus(v)==='published'&&url(v.url);return `<article class="card recording-card ${ready?'':'is-pending'}"><span class="content-status ${ready?'is-published':'is-upcoming'}">${ready?'Запись опубликована':'Видео ещё не опубликовано'}</span>${v.date?`<time datetime="${esc(v.date)}">${esc(dateLabel(v.date))}</time>`:''}<h3>${esc(v.title)}</h3>${v.text?`<p class="block-text">${esc(v.text)}</p>`:''}${ready?link(v.url,'Смотреть запись'):'<p class="muted">Ссылка появится после публикации видео.</p>'}</article>`;}).join('')}</div></div>`).join('')||'<div class="empty">Записи появятся здесь после занятий.</div>';
    }
    if(b.type==='materials'&&d.resources?.length){const items=[...(d.url?[{title:'Папка вашей группы',url:d.url}]:[]),...d.resources];return `${d.text?`<p class="block-text">${esc(d.text)}</p>`:''}<div class="resource-grid">${items.map(r=>`<article class="card resource-card"><h3>${esc(r.title)}</h3>${r.text?`<p class="block-text">${esc(r.text)}</p>`:''}${link(r.url,r.button||'Открыть материалы')}</article>`).join('')}</div>`;}
    if(b.type==='materials')return `<div class="folder-banner"><svg width="92" height="72" viewBox="0 0 92 72" aria-hidden="true"><path d="M4 14h30l9 10h45v42H4z" fill="none" stroke="currentColor" stroke-width="3"/><path d="M4 24V6h31l9 8h39v10" fill="none" stroke="currentColor" stroke-width="3"/></svg><div><h3>Папка вашей группы</h3><p>${esc(d.text)}</p>${link(d.url,'Перейти к материалам','btn')||'<p class="muted">Ссылка скоро появится.</p>'}</div></div>`;
    return `<div class="card information-block">${image(d)}<p class="block-text">${esc(d.text||(b.type==='join'?'Информация о подключении появится перед занятием.':'Администратор добавит информацию.'))}</p>${b.type==='join'&&d.platform?`<p class="muted">Платформа: ${esc(d.platform)}</p>`:''}${link(d.url,d.button||({join:'Подключиться',video:'Смотреть видео',introductions:'Перейти в Holst'}[b.type]||'Подробнее'))}</div>`;
  };
  const render=(ctx,g)=>{
    GroupHero.render(document.querySelector('.course-hero'),{...g,program_cover_url:ctx.programs[g.program_id]?.cover_url,program_type:ctx.programs[g.program_id]?.program_type});
    const blocks=getBlocks(g).filter(b=>labels[b.type]&&b.data.visible!==false);
    const nav=document.querySelector('.course-nav-inner');
    nav.innerHTML=blocks.map((b,i)=>`<a href="#block-${i}">${esc(b.data.title||labels[b.type])}</a>`).join('')+'<a href="#help">Помощь</a>';
    const main=document.querySelector('main.shell');
    const help=`<section id="help" class="section"><div class="section-title"><h2>Ответы на популярные вопросы</h2></div><details><summary>К кому обратиться с вопросом об обучении?</summary><p>Ваш администратор — ${esc(g.owner)}. Контакты и чаты указаны в начале страницы.</p></details><details><summary>Как добавить ещё одну программу?</summary><p>Откройте «Моё обучение» и нажмите «Добавить обучение». Новый аккаунт создавать не нужно.</p></details><details><summary>До какого числа доступны материалы?</summary><p>Общий срок группы — до ${esc(dateLabel(ctx.closes(g)))} включительно. Индивидуальное продление уточняйте у администратора.</p></details></section>`;
    main.innerHTML=blocks.map((b,i)=>`<section id="block-${i}" class="section"><div class="section-title"><h2>${esc(b.data.title||labels[b.type])}</h2></div>${b.type==='alias'?(resolveAlias(b,ctx.groups)?content(resolveAlias(b,ctx.groups).block,resolveAlias(b,ctx.groups).group):'<div class="empty">Содержимое временно недоступно. Администратор проверит подключение блока.</div>'):content(b,g)}</section>`).join('')+help;
    if(new URLSearchParams(location.search).has('preview')){
      const bar=document.createElement('div');bar.className='page-edit-banner';bar.innerHTML=`<span>Предпросмотр страницы группы</span><a class="text-link" href="editor.html?group=${encodeURIComponent(ctx.groupId)}&role=${ctx.role}">Редактировать страницу ЛК →</a>`;document.querySelector('.course-hero').before(bar);
    }
  };
  const initEditor=ctx=>{
    const query=new URLSearchParams(location.search),pid=query.get('program'),gid=query.get('group');
    const isProgram=Boolean(pid),record=isProgram?ctx.programs[pid]:ctx.groups[gid];
    const root=document.getElementById('page-editor');if(!root)return;
    if(!record){root.innerHTML='<h1>Страница не найдена</h1><p>Вернитесь к списку групп или программ.</p>';return;}
    const allowed=isProgram?ctx.senior:ctx.canEditGroup(gid);
    let blocks=isProgram?getBlocks({page_blocks:record.page_blocks??template(record.template_key||'mba')}):getBlocks(record);
    let dirty=false;
    root.innerHTML=`<div class="page-heading"><div><div class="eyebrow muted">${isProgram?'Шаблон программы':'Страница группы'}</div><h1>${esc(record.title||record.name)}</h1><p>${isProgram?'Новые группы получат копию этих блоков. Уже созданные группы сохранят своё наполнение.':'Меняйте содержание, порядок и видимость блоков этой группы.'}</p></div><a class="btn outline" href="admin.html?role=${ctx.role}&panel=${isProgram?'programs':'groups'}">Назад к ${isProgram?'программам':'группам'}</a></div><div class="editor-toolbar"><span>${allowed?'Обложка и приветствие — в разделе «Главный экран» ниже.':'Только просмотр — редактирование доступно администратору группы.'}</span>${!isProgram?`<a class="text-link" href="${ctx.groupLink(gid)}&preview=1&role=${ctx.role}" target="_blank" rel="noopener">Посмотреть страницу ↗</a>`:''}</div><form id="blocks-form"><fieldset ${allowed?'':'disabled'}><div id="blocks-list"></div><div class="editor-add"><label class="field">Новый блок<select id="block-type">${Object.entries(labels).map(([k,v])=>`<option value="${k}">${v}</option>`).join('')}</select></label><button type="button" class="btn outline" id="add-block">Добавить блок</button></div></fieldset><div class="editor-save"><span id="save-state" role="status">Изменений пока нет</span>${allowed?'<button class="btn" type="submit">Сохранить страницу</button>':''}</div></form><p class="form-note">Изменения сохраняются в этом браузере. Для публикации на сайте передайте правки разработчику.</p>`;
    const form=root.querySelector('form'),list=root.querySelector('#blocks-list');
    const mark=()=>{dirty=true;root.querySelector('#save-state').textContent='Есть несохранённые изменения';};
    const heroEditor=!isProgram?GroupHero.editor({root:form.querySelector('fieldset'),record,allowed,mark,escape:esc,programCover:ctx.programs[record.program_id]?.cover_url,programType:ctx.programs[record.program_id]?.program_type}):null;
    if(isProgram){root.querySelector('.editor-toolbar span').textContent='Шаблон содержания для новых групп этой программы.';if(SchoolPrograms.safeCover(record.cover_url)){const cover=document.createElement('div');cover.className='editor-block';cover.innerHTML=`<h2>Обложка программы</h2><img class="program-cover" src="${esc(SchoolPrograms.safeCover(record.cover_url))}" alt="${esc(record.title)}"><p class="form-note">Используется в ЛК группы, пока для неё не загружена собственная обложка.</p>`;form.querySelector('fieldset').prepend(cover);}}
    const field=(i,key,label,value,kind='text',attrs='')=>`<label class="field">${label}${kind==='textarea'?`<textarea data-key="${key}" rows="3" maxlength="5000" ${attrs}>${esc(value)}</textarea>`:`<input data-key="${key}" type="${kind}" value="${esc(value)}" ${attrs}>`}</label>`;
    const rowFields={
      chats:[['label','Название чата'],['url','Ссылка','url']],
      lessons:[['source_id','ID занятия (для обновления из таблицы)'],['date','Дата','date'],['time','Время ЕКБ (17:00–21:00)'],['title','Занятие'],['teacher','Преподаватель'],['hours','Академические часы'],['module','Модуль'],['format','Формат занятия'],['url','Ссылка на занятие','url']],
      videos:[['module','Модуль'],['title','Название записи'],['date','Дата занятия','date'],['status','Статус записи','select'],['text','Описание','textarea'],['url','Ссылка на запись','url']],
      tasks:[['module','Модуль'],['deadline','Срок','date'],['status','Статус задания','select'],['title','Задание','textarea'],['url','Ссылка на материалы','url'],['button','Текст ссылки']],
      questions:[['question','Вопрос'],['answer','Ответ','textarea'],['url','Ссылка в ответе','url'],['button','Текст ссылки']],
      resources:[['title','Название материала'],['text','Описание','textarea'],['url','Ссылка на материал','url'],['button','Текст кнопки']]
    };
    const rowField=(key,r,k,l,t)=>{
      if(t==='select'){
        const values=key==='videos'?{pending:'Видео ещё не опубликовано',published:'Запись опубликована'}:{upcoming:'Предстоит',past:'Прошедшее'};
        const selected=key==='videos'?recordingStatus(r):(r.status||'upcoming');
        return `<label class="field">${l}<select data-key="${k}">${Object.entries(values).map(([v,label])=>`<option value="${v}" ${v===selected?'selected':''}>${label}</option>`).join('')}</select></label>`;
      }
      const required=['title','label','question','answer'].includes(k)||k==='date'&&key==='lessons'||k==='url'&&(['chats','resources'].includes(key)||key==='videos'&&recordingStatus(r)==='published');
      const attrs=k==='time'?'required pattern="([01]?[0-9]|2[0-3])[:.][0-5][0-9] *[-–—] *([01]?[0-9]|2[0-3])[:.][0-5][0-9]"':required?'required':'';
      return field(0,k,l,r[k],t||'text',attrs);
    };
    const rows=(d,key)=>`<div class="editor-rows" data-rows="${key}">${(d[key]||[]).map((r,n)=>`<div class="editor-row" data-row="${n}"><div class="form-grid">${rowFields[key].map(([k,l,t])=>rowField(key,r,k,l,t)).join('')}</div><button type="button" class="text-button danger" data-remove-row="${key}" data-row-index="${n}">Удалить строку</button></div>`).join('')}<button type="button" class="btn outline" data-add-row="${key}">Добавить ${{chats:'чат',lessons:'занятие',videos:'запись',tasks:'задание',questions:'вопрос',resources:'материал'}[key]}</button></div>`;
    const draw=()=>{
      list.innerHTML=blocks.map((b,i)=>{const d=b.data;let fields='';
        if(b.type==='contacts')fields=`<div class="alias-note">Фото и общие контакты меняются в профиле сотрудника. <a href="admin.html?role=${ctx.role}&panel=profiles" target="_blank" rel="noopener">Открыть «Администраторы и фото» ↗</a></div>`+field(i,'text','Описание',d.text,'textarea')+field(i,'contact_url','Ссылка для связи с администратором',d.contact_url,'url')+field(i,'chat_text','Описание чатов',d.chat_text??chatDescription,'textarea')+rows(d,'chats');
        else if(b.type==='alias'){
          const sources=Object.entries(ctx.groups).filter(([id])=>id!==gid&&(ctx.canEditGroup(id)||id===d.source_group));
          const source=ctx.groups[d.source_group],sourceBlocks=source?getBlocks(source).filter(x=>x.type!=='alias'):[];
          fields=`<div class="alias-note"><strong>Содержимое обновляется из исходного ЛК</strong><p>Редактируйте записи в источнике. Здесь можно менять заголовок, порядок и видимость подключения.</p>${source?`<a href="editor.html?group=${encodeURIComponent(d.source_group)}&role=${ctx.role}" target="_blank" rel="noopener">Источник: ${esc(source.name)} / ${esc(source.code)} ↗</a>`:''}</div><label class="field">ЛК-источник<select data-key="source_group"><option value="">Выберите группу</option>${sources.map(([id,g])=>`<option value="${id}" ${id===d.source_group?'selected':''}>${esc(g.name)} — ${esc(g.code)}</option>`).join('')}</select></label><label class="field">Исходный блок<select data-key="source_block" required><option value="">Выберите блок</option>${sourceBlocks.map(x=>`<option value="${esc(x.data.block_id)}" ${x.data.block_id===d.source_block?'selected':''}>${esc(x.data.title||labels[x.type])}</option>`).join('')}</select></label>`;
        }
        else if(b.type==='schedule')fields=`<label class="check"><input data-key="show_moscow" type="checkbox" ${d.show_moscow?'checked':''}>Показывать время Москвы рядом с Екатеринбургом</label><label class="check"><input data-key="collapse_past" type="checkbox" ${d.collapse_past?'checked':''}>Сворачивать полностью прошедшие месяцы</label>${field(i,'source_url','Ссылка на Google Таблицу или файл расписания',d.source_url,'url')}${!isProgram?'<button type="button" class="btn outline" data-import-schedule>Обновить из Google Таблицы / CSV</button>':''}<p class="form-note">Можно заполнить вручную или загрузить таблицу с предпросмотром изменений.</p>${rows(d,'lessons')}`;
        else if(b.type==='recordings')fields='<p class="form-note">Для будущего занятия выберите «Видео ещё не опубликовано». Ссылку можно добавить позже в ту же карточку.</p>'+rows(d,'videos');
        else if(b.type==='homework')fields='<p class="form-note">Общий статус для группы: «Прошедшее» или «Предстоит». Это не персональная отметка выполнения.</p>'+rows(d,'tasks');
        else if(b.type==='faq')fields=rows(d,'questions');
        else if(b.type==='materials')fields=field(i,'text','Описание',d.text,'textarea')+field(i,'url','Ссылка на папку группы (необязательно)',d.url,'url')+rows(d,'resources');
        else fields=field(i,'text','Текст',d.text,'textarea')+(b.type==='join'?field(i,'platform','Платформа (Zoom, Контур.Толк и другие)',d.platform):'')+field(i,'url',b.type==='materials'?'Ссылка на папку группы':'Ссылка',d.url,'url')+(b.type==='materials'?'':field(i,'button','Текст кнопки',d.button));
        if(['video','introductions'].includes(b.type))fields+=field(i,'image_url','Ссылка на изображение (необязательно)',d.image_url,'url')+field(i,'image_alt','Описание изображения',d.image_alt);
        return `<details class="editor-block" data-block="${i}" open><summary>${esc(d.title||labels[b.type])}<small>${esc(labels[b.type])}</small></summary><div class="editor-block-body"><div class="block-controls"><label class="check"><input data-key="visible" type="checkbox" ${d.visible!==false?'checked':''}>Показывать блок</label><button type="button" data-move="-1" ${i===0?'disabled':''} aria-label="Поднять блок">↑ Выше</button><button type="button" data-move="1" ${i===blocks.length-1?'disabled':''} aria-label="Опустить блок">↓ Ниже</button><button type="button" data-remove-block class="danger">Удалить блок</button></div>${field(i,'title','Заголовок блока',d.title,'text','required maxlength="180"')}${!isProgram&&b.type!=='alias'&&usedBy(gid,d.block_id,ctx.groups).length?`<div class="alias-note"><strong>Этот блок — источник для других ЛК</strong><p>Изменения отобразятся в: ${usedBy(gid,d.block_id,ctx.groups).map(([id,g])=>`<a href="editor.html?group=${encodeURIComponent(id)}&role=${ctx.role}" target="_blank" rel="noopener">${esc(g.name)} / ${esc(g.code)}</a>`).join(', ')}.</p><p>Если скрыть или удалить источник, связанные блоки станут недоступны.</p></div>`:''}${fields}</div></details>`;
      }).join('');
    };
    const sync=()=>{list.querySelectorAll('[data-block]').forEach(el=>{const d=blocks[Number(el.dataset.block)].data;el.querySelectorAll('[data-key]').forEach(input=>{const row=input.closest('[data-row]');const obj=row?d[row.closest('[data-rows]').dataset.rows][Number(row.dataset.row)]:d;obj[input.dataset.key]=input.type==='checkbox'?input.checked:input.value.trim();});});};
    list.addEventListener('input',mark);
    list.addEventListener('change',event=>{if(event.target.dataset.key==='status'&&event.target.closest('[data-rows=videos]')){const input=event.target.closest('[data-row]').querySelector('[data-key=url]');input.required=event.target.value==='published';input.setCustomValidity('');}if(event.target.dataset.key==='source_group'){sync();blocks[Number(event.target.closest('[data-block]').dataset.block)].data.source_block='';mark();draw();}});
    list.addEventListener('click',event=>{const button=event.target.closest('button'),el=button?.closest('[data-block]');if(!el||!allowed)return;sync();const i=Number(el.dataset.block),d=blocks[i].data;
      if(button.hasAttribute('data-import-schedule')){ScheduleImportUI.open({data:d,group:record,onApply:(lessons,source)=>{d.lessons=lessons;d.source_url=source;mark();draw();}});return;}
      if(button.hasAttribute('data-remove-block'))blocks.splice(i,1);
      else if(button.dataset.move){const to=i+Number(button.dataset.move);[blocks[i],blocks[to]]=[blocks[to],blocks[i]];}
      else if(button.dataset.addRow)(d[button.dataset.addRow]??=[]).push(button.dataset.addRow==='videos'?{status:'pending'}:{});
      else if(button.dataset.removeRow)d[button.dataset.removeRow].splice(Number(button.dataset.rowIndex),1);
      else return;mark();draw();
    });
    root.querySelector('#add-block').addEventListener('click',()=>{if(!allowed)return;sync();blocks.push(block(root.querySelector('#block-type').value));mark();draw();list.lastElementChild.scrollIntoView({block:'start',behavior:'smooth'});});
    form.addEventListener('submit',event=>{event.preventDefault();if(!allowed)return;if(heroEditor&&!heroEditor.save())return;sync();let invalid=false;form.querySelectorAll('input[type=url]').forEach(input=>{input.setCustomValidity(input.value&&!url(input.value)?'Укажите http:// или https:// ссылку':'');if(!input.checkValidity())invalid=true;});if(invalid){form.reportValidity();return;}record.page_blocks=clone(blocks);if(!isProgram)ctx.state.groupOverrides[gid]=record;if(!ctx.save()){root.querySelector('#save-state').textContent='Не удалось сохранить в браузере. Изменения остаются в форме.';return;}dirty=false;root.querySelector('#save-state').textContent='Сохранено. Можно открыть предпросмотр.';});
    form.addEventListener('input',event=>event.target.setCustomValidity?.(''));
    window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});
    draw();
  };
  return {templates,template,getBlocks,initEditor,render,schedule};
})();
