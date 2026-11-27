(() => {
  'use strict';
  const scriptUrl = document.currentScript.src;
  const url = (name) => new URL(name, scriptUrl).href;
  const pageUrl = (params = '') => url('support.html') + params;
  const escape = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const statuses = {new:'Новое',in_progress:'В работе',resolved:'Решено',closed:'Закрыто'};
  const acceptTicket = ticket => {if(!ticket.takenAt){ticket.takenAt=new Date().toISOString();ticket.takenBy='Мария';}};
  const takenTime = value => new Date(value).toLocaleString('ru-RU',{dateStyle:'short',timeStyle:'short',timeZone:'Asia/Yekaterinburg'})+' ЕКБ';
  const isClosed = ticket => ['resolved','closed'].includes(ticket.status);
  const noticeKey = 'vesh-support-notifications-v1';
  const notices = () => { try { return JSON.parse(localStorage.getItem(noticeKey)||'[]'); } catch { return []; } };
  function saveNotices(rows) { localStorage.setItem(noticeKey,JSON.stringify(rows)); window.dispatchEvent(new Event('support-notifications-changed')); }
  const categories = {access:'Доступ и вход',video:'Видео',materials:'Материалы и задания',other:'Другая проблема'};
  const demoNotice = 'Демонстрация: сообщения и файлы сохраняются только в этом браузере, в техподдержку не отправляются.';
  const date = (value) => new Date(value).toLocaleString('ru-RU',{dateStyle:'short',timeStyle:'short'});
  const stamp = (ticket) => `<span class="support-status support-status-${ticket.status}">${statuses[ticket.status]}</span>`;
  const db = new Promise((resolve,reject) => {
    const request = indexedDB.open('vesh-support-demo-v1',1);
    request.onupgradeneeded = () => request.result.createObjectStore('tickets',{keyPath:'id',autoIncrement:true});
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });
  async function action(mode,fn) {
    const database = await db;
    return new Promise((resolve,reject) => {
      const transaction = database.transaction('tickets',mode);
      const request = fn(transaction.objectStore('tickets'));
      transaction.oncomplete = () => resolve(request.result);
      transaction.onerror = () => reject(transaction.error);
      transaction.onabort = () => reject(transaction.error);
    });
  }
  const files = (id) => `<div class="support-upload"><label for="${id}-files">Скриншот или документ <span class="support-optional">необязательно</span></label><input id="${id}-files" type="file" name="attachments" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt" data-support-files aria-describedby="${id}-hint"><p id="${id}-hint" class="support-hint">До 3 файлов по 5 МБ. JPG, PNG, WebP, PDF, Word, Excel или TXT.</p><p class="support-file-status" data-support-file-status aria-live="polite"></p></div>`;
  const newForm = `<form class="support-form" id="support-demo-new" data-support-form><label for="support-demo-category">С чем нужна помощь?</label><select id="support-demo-category" name="category">${Object.entries(categories).map(([k,v])=>`<option value="${k}" ${k==='other'?'selected':''}>${v}</option>`).join('')}</select><label for="support-demo-subject">Тема обращения</label><input id="support-demo-subject" name="subject" maxlength="160" placeholder="Например, не открывается видео урока" required><label for="support-demo-body">Что произошло?</label><textarea id="support-demo-body" name="body" rows="4" maxlength="5000" placeholder="Опишите, что вы хотели сделать и на каком шаге возникла сложность" required></textarea>${files('support-demo-new')}<p class="support-related" data-repeat-reference hidden></p><p class="support-hint">Страница обращения будет добавлена автоматически. Ответ появится в ЛК.</p><p class="support-hint">${demoNotice}</p><p role="alert" data-demo-error></p><div class="support-actions"><button type="submit" class="support-primary">Отправить обращение</button><a href="${pageUrl()}" class="support-text-link">Мои обращения</a></div></form>`;
  const style = document.createElement('link'); style.rel='stylesheet'; style.href=url('support.css?v=20261127-support-3');document.head.append(style);
  const widget = document.createElement('div');
  widget.innerHTML = `<a href="${pageUrl('?new=1')}" class="support-launcher" data-support-open aria-haspopup="dialog" aria-controls="support-dialog"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H5l-3 2V11.5a9.5 9.5 0 0 1 19 0Z"/><path d="M9 9a3 3 0 1 1 5 2.2c-1 .6-2 1-2 2.3M12 16v.5"/></svg><span>Помощь</span></a><dialog id="support-dialog" class="support-dialog" aria-labelledby="support-dialog-title"><div class="support-dialog-head"><div><span class="support-eyebrow">Мы рядом</span><h2 id="support-dialog-title">Техническая поддержка</h2></div><button type="button" class="support-close" data-support-close aria-label="Закрыть форму">×</button></div><p class="support-lead">Не открывается видео, материал или нужный раздел? Расскажите нам — поможем разобраться.</p>${newForm}</dialog>`;
  document.body.append(widget);
  const dialog = document.getElementById('support-dialog');
  const query = new URL(location.href).searchParams;
  const operator = query.get('mode') === 'staff';
  let enabled = localStorage.getItem('vesh-support-demo-permission') === 'true';
  let current;
  let previousTicket = null;
  let newSaving = false;
  const validateFiles = (form) => {
    const input=form.querySelector('input[type=file]');const list=[...(input?.files||[])];
    if(list.length>3||list.some(file=>file.size>5*1024*1024||!/\.(jpe?g|png|webp|pdf|docx?|xlsx?|txt)$/i.test(file.name))) throw new Error('Проверьте вложения: до 3 файлов по 5 МБ, изображения или документы.');
    return list;
  };
  document.getElementById('support-demo-new').addEventListener('submit',async(event)=>{
    event.preventDefault();const form=event.currentTarget;if(newSaving)return;
    const button=form.querySelector('button[type=submit]');
    try {
      const attachments=validateFiles(form);const values=new FormData(form);const subject=String(values.get('subject')).trim();const body=String(values.get('body')).trim();
      if(!subject||!body)throw new Error('Заполните тему и описание проблемы.');
      newSaving=true;
      const now=new Date().toISOString();
      const id=await action('readwrite',store=>store.add({parentId:previousTicket?.id||null,subject,category:values.get('category'),status:'new',created:now,origin:previousTicket?.origin||location.pathname,originTitle:previousTicket?.originTitle||document.title,messages:[{body,attachments,staff:false,created:now}]}));
      location.href=pageUrl('?ticket='+id+'&sent=1');
    }catch(error){form.querySelector('[data-demo-error]').textContent=error.message||'Не удалось сохранить. Разрешите хранение данных в браузере и повторите.';newSaving=false;button.disabled=false;button.textContent='Отправить обращение';}
  });
  const main=document.getElementById('support-demo-page');
  async function render() {
    const records=(await action('readonly',store=>store.getAll())).sort((a,b)=>b.id-a.id);
    syncNotices(records);
    if(!main)return;
    const selected=Number(query.get('ticket'));
    current=records.find(t=>t.id===selected);
    if (current && !operator) saveNotices(notices().map(n=>n.ticketId===current.id?{...n,read:true}:n));
    const toolbar=`<div class="support-panel" style="padding:14px 18px;margin-bottom:24px"><p class="support-hint">${demoNotice}</p><div class="support-actions" style="margin-top:8px"><a class="support-text-link" href="${pageUrl()}">Вид слушателя</a><a class="support-text-link" href="${pageUrl('?mode=staff')}">Вид сотрудника</a><label style="font-size:12px"><input type="checkbox" id="demo-support-permission" ${enabled?'checked':''}> Выдана функция техподдержки</label></div></div>`;
    if(operator&&!enabled){main.innerHTML=toolbar+`<div class="support-empty"><h1>Нет доступа к техподдержке</h1><p>Старший администратор должен включить сотруднику функцию «Обрабатывает обращения в техподдержку».</p><p class="support-hint">В демонстрации это можно проверить переключателем выше.</p></div>`;bind();return;}
    if(!current){
      main.innerHTML=toolbar+`<a class="support-back" href="${url('courses.html')}">← Моё обучение</a><div class="support-page-heading"><div><span class="support-eyebrow">Помощь в личном кабинете</span><h1>${operator?'Техподдержка':'Мои обращения'}</h1><p>${operator?'Общая очередь технических обращений':'Здесь сохраняются ваши вопросы и ответы техподдержки.'}</p></div><button class="support-primary" id="demo-new-button">Новое обращение</button></div><div class="support-ticket-list">${records.length?records.map(t=>`<a class="support-ticket-card" href="${pageUrl('?ticket='+t.id+(operator?'&mode=staff':''))}"><div><span class="support-ticket-meta">№${t.id} — ${date(t.created)}</span><h2>${escape(t.subject)}</h2><span class="support-hint">${categories[t.category]}</span>${operator?`<span class="support-ticket-assignment">${t.takenAt?'Взял в работу: '+escape(t.takenBy)+'<br>'+takenTime(t.takenAt):'Пока никто не взял в работу'}</span>`:''}</div>${stamp(t)}</a>`).join(''):`<div class="support-empty"><span class="support-empty-icon" aria-hidden="true">?</span><h2>Пока нет обращений</h2><p>Если возникнут сложности с личным кабинетом,<br>напишите нам через кнопку «Помощь».</p></div>`}</div>`;
      bind();document.getElementById('demo-new-button').addEventListener('click',()=>openNew());return;
    }
    main.classList.add('support-page-narrow');
    const linkTo = id => pageUrl('?ticket='+id+(operator?'&mode=staff':''));
    const relations = (current.parentId ? `<p class="support-related">Повторное обращение по <a class="support-text-link" href="${linkTo(current.parentId)}">обращению №${current.parentId}</a></p>` : '') + records.filter(t=>t.parentId===current.id).map(t=>`<p class="support-related">Продолжение: <a class="support-text-link" href="${linkTo(t.id)}">обращение №${t.id}</a></p>`).join('');
    main.innerHTML=toolbar+`<a class="support-back" href="${pageUrl(operator?'?mode=staff':'')}">← ${operator?'Очередь техподдержки':'Мои обращения'}</a>${query.has('sent')?'<div class="support-success" role="status">✓ Обращение сохранено в демонстрации</div>':''}
      <div class="support-thread-heading"><div class="support-thread-meta"><span>Обращение №${current.id}</span>${stamp(current)}</div><h1>${escape(current.subject)}</h1><p class="support-hint">${categories[current.category]} — ${date(current.created)}</p><p class="support-hint">Страница обращения: ${escape(current.originTitle)}</p>${relations}</div>
      ${current.takenAt?`<aside class="support-assignment" aria-label="Принятие обращения в работу"><span class="support-assignment-icon" aria-hidden="true">✓</span><div><strong>Обращение взято в работу</strong><p>${operator?'Ответственный: '+escape(current.takenBy):'Техподдержка приняла ваше обращение'}</p><time>${takenTime(current.takenAt)}</time></div></aside>`:operator&&!isClosed(current)?'<p class="support-assignment-pending">Обращение пока никто не взял в работу</p>':''}
      ${!isClosed(current)?`<div class="support-status-form">${operator&&!current.takenAt?'<button class="support-secondary" data-set-status="in_progress">Взять в работу</button>':''}<button class="support-secondary" data-set-status="resolved">Проблема решена</button><button class="support-secondary" data-set-status="closed">Закрыть обращение</button><span class="support-hint">Переписка сохранится в истории</span></div>`:''}
      <section class="support-messages" aria-label="Переписка по обращению">${current.messages.map((message,mi)=>`<article class="support-message ${message.staff?'support-message-staff':''}"><header><strong>${message.staff?(operator?'Техподдержка — Мария':'Техподдержка'):'Анна Петрова'}</strong><time>${date(message.created)}</time></header><p class="support-message-body">${escape(message.body)}</p><ul class="support-attached">${message.attachments.map((file,fi)=>`<li><a href="#" data-demo-download="${mi}:${fi}">↓ ${escape(file.name)}</a><span>${Math.ceil(file.size/1024)} КБ</span></li>`).join('')}</ul>${operator&&message.emailRequested?'<p class="support-mail-status">Выбрана отправка на почту student@example.test<br>Демонстрация — письмо не отправляется</p>':''}</article>`).join('')}</section>
      ${isClosed(current)?`<section class="support-panel"><h2>${current.status==='resolved'?'Проблема решена':'Обращение закрыто'}</h2><p>Переписка сохранена и доступна для просмотра</p>${!operator?`<p class="support-hint">Если сложность осталась, создайте новое обращение — ссылка на эту переписку добавится автоматически</p><div class="support-actions"><button class="support-primary" id="demo-repeat">Обратиться повторно</button></div>`:''}</section>`:`<section class="support-panel"><h2>${operator?'Ответить слушателю':'Добавить сообщение'}</h2><form class="support-form" id="demo-reply-form"><label for="demo-reply">Сообщение</label><textarea id="demo-reply" name="body" rows="4" maxlength="5000" required></textarea>${files('demo-reply')}${operator?'<label class="support-email-option"><input type="checkbox" name="send_email"><span>Отправить также на почту<br><span class="support-hint">student@example.test</span></span></label><p class="support-hint">Ответ и уведомление появятся в ЛК в любом случае<br>В демонстрации настоящие письма не отправляются</p>':''}<p data-demo-error role="alert"></p><div class="support-actions"><button class="support-primary">Отправить сообщение</button></div></form></section>`}`;
    bind();
    main.querySelectorAll('[data-demo-download]').forEach(link=>link.addEventListener('click',(event)=>{event.preventDefault();const [mi,fi]=link.dataset.demoDownload.split(':').map(Number);const file=current.messages[mi].attachments[fi];const href=URL.createObjectURL(file);const a=document.createElement('a');a.href=href;a.download=file.name;a.click();setTimeout(()=>URL.revokeObjectURL(href),1000);}));
    document.getElementById('demo-repeat')?.addEventListener('click',()=>openNew(current));
    main.querySelectorAll('[data-set-status]').forEach(button=>button.addEventListener('click',async()=>{
      if(isClosed(current))return;
      button.disabled=true;
      try { current=await action('readonly',store=>store.get(current.id));if(isClosed(current)){await render();return;}current.status=button.dataset.setStatus;if(current.status==='in_progress')acceptTicket(current); if(isClosed(current))current.closedAt=new Date().toISOString(); await action('readwrite',store=>store.put(current));await render(); }
      catch { button.disabled=false;showError(); }
    }));
    let replying=false;
    document.getElementById('demo-reply-form')?.addEventListener('submit',async(event)=>{
      event.preventDefault();if(replying)return;const form=event.currentTarget;
      try{const attachments=validateFiles(form);const body=form.elements.body.value.trim();if(!body)throw new Error('Напишите сообщение.');replying=true;form.querySelector('button').disabled=true;const latest=await action('readonly',store=>store.get(current.id));if(isClosed(latest))throw new Error('Обращение уже закрыто. Создайте повторное обращение.');const next=structuredClone(latest);next.messages.push({body,attachments,staff:operator,emailRequested:operator&&form.elements.send_email.checked,created:new Date().toISOString()});if(operator)acceptTicket(next);next.status=next.takenAt?'in_progress':'new';await action('readwrite',store=>store.put(next));await render();}catch(error){form.querySelector('[data-demo-error]').textContent=error.message;form.querySelector('button').disabled=false;replying=false;}
    });
  }
  function openNew(parent = null) {
    previousTicket = parent;
    const form=document.getElementById('support-demo-new');
    form.reset();form.elements.subject.value=parent?.subject||'';form.elements.category.value=parent?.category||'other';
    form.querySelector('[data-demo-error]').textContent='';
    const ref=form.querySelector('[data-repeat-reference]');ref.hidden=!parent;
    ref.innerHTML=parent?`Повторное обращение по <a class="support-text-link" href="${pageUrl('?ticket='+parent.id)}">обращению №${parent.id}</a><br>Опишите, какая сложность осталась`:'';
    dialog.showModal();
  }
  document.querySelector('[data-support-open]').addEventListener('click',()=>{previousTicket=null;document.querySelector('[data-repeat-reference]').hidden=true;});
  function syncNotices(records) {
    const rows=notices();let changed=false;
    for(const t of records) for(const [i,m] of t.messages.entries()) if(m.staff&&!rows.some(n=>n.id===`${t.id}:${i}`)) {
      rows.push({id:`${t.id}:${i}`,ticketId:t.id,kind:'support_reply',tag:'Техподдержка ответила на обращение №'+t.id,body:t.subject,from:m.created,sender:'Техподдержка',read:false});changed=true;
    }
    if(changed)saveNotices(rows);else refreshNoticeButton();
  }
  function inbox() {
    const rows=notices().sort((a,b)=>Date.parse(b.from)-Date.parse(a.from));
    const panel=document.createElement('dialog');panel.className='support-dialog';panel.setAttribute('aria-label','Уведомления');
    panel.innerHTML=`<div class="support-dialog-head"><h2>Уведомления</h2><button class="support-close" aria-label="Закрыть уведомления">×</button></div><div class="support-notice-list">${rows.length?rows.map(n=>`<a class="${n.read?'':'support-notice-unread'}" href="${pageUrl('?ticket='+n.ticketId)}"><strong>${escape(n.tag)}</strong><span>${escape(n.body)}</span><small>${date(n.from)}${n.read?'':' — Новое'}</small></a>`).join(''):'<p>Здесь появятся ответы техподдержки</p>'}</div>`;
    panel.querySelector('button').onclick=()=>panel.close();panel.onclose=()=>panel.remove();document.body.append(panel);panel.showModal();
  }
  function refreshNoticeButton() {
    const count=notices().filter(n=>!n.read).length;
    document.querySelectorAll('[data-support-notices]').forEach(button=>{button.innerHTML='Уведомления'+(count?` <b>${count}</b>`:'');button.setAttribute('aria-label','Уведомления'+(count?', непрочитанных: '+count:''));});
  }
  function addNoticeButton(host) {
    const button=document.createElement('button');button.className='support-notice-button';button.dataset.supportNotices='';button.onclick=inbox;host.append(button);refreshNoticeButton();
  }
  const nav=document.querySelector('.top-nav');
  if(main&&nav)addNoticeButton(nav);
  addNoticeButton(dialog);
  window.addEventListener('support-notifications-changed',refreshNoticeButton);
  window.addEventListener('storage',event=>{if(event.key===noticeKey)refreshNoticeButton();});
  function bind(){document.getElementById('demo-support-permission')?.addEventListener('change',event=>{enabled=event.target.checked;localStorage.setItem('vesh-support-demo-permission',String(enabled));render().catch(showError);});}
  function showError(){if(main)main.innerHTML='<div class="support-errors" role="alert">Не удалось открыть демонстрацию. Разрешите сохранение данных в браузере и обновите страницу.</div>';}
  render().catch(showError);
  const shared=document.createElement('script');shared.src=url('support.js?v=20261127-support-3');shared.onload=()=>{if(query.has('new'))openNew();};document.body.append(shared);
})();
