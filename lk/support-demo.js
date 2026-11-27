(() => {
  'use strict';
  const scriptUrl = document.currentScript.src;
  const url = (name) => new URL(name, scriptUrl).href;
  const pageUrl = (params = '') => url('support.html') + params;
  const escape = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const statuses = {new:'Новое',in_progress:'В работе',resolved:'Решено'};
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
  const newForm = `<form class="support-form" id="support-demo-new" data-support-form><label for="support-demo-category">С чем нужна помощь?</label><select id="support-demo-category" name="category">${Object.entries(categories).map(([k,v])=>`<option value="${k}" ${k==='other'?'selected':''}>${v}</option>`).join('')}</select><label for="support-demo-subject">Тема обращения</label><input id="support-demo-subject" name="subject" maxlength="160" placeholder="Например, не открывается видео урока" required><label for="support-demo-body">Что произошло?</label><textarea id="support-demo-body" name="body" rows="4" maxlength="5000" placeholder="Опишите, что вы хотели сделать и на каком шаге возникла сложность" required></textarea>${files('support-demo-new')}<p class="support-hint">Страница обращения будет добавлена автоматически. Ответ появится в ЛК.</p><p class="support-hint">${demoNotice}</p><p role="alert" data-demo-error></p><div class="support-actions"><button type="submit" class="support-primary">Отправить обращение</button><a href="${pageUrl()}" class="support-text-link">Мои обращения</a></div></form>`;
  const style = document.createElement('link'); style.rel='stylesheet'; style.href=url('support.css?v=20261127-support-1');document.head.append(style);
  const widget = document.createElement('div');
  widget.innerHTML = `<a href="${pageUrl('?new=1')}" class="support-launcher" data-support-open aria-haspopup="dialog" aria-controls="support-dialog"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H5l-3 2V11.5a9.5 9.5 0 0 1 19 0Z"/><path d="M9 9a3 3 0 1 1 5 2.2c-1 .6-2 1-2 2.3M12 16v.5"/></svg><span>Помощь</span></a><dialog id="support-dialog" class="support-dialog" aria-labelledby="support-dialog-title"><div class="support-dialog-head"><div><span class="support-eyebrow">МЫ РЯДОМ</span><h2 id="support-dialog-title">Техническая поддержка</h2></div><button type="button" class="support-close" data-support-close aria-label="Закрыть форму">×</button></div><p class="support-lead">Не открывается видео, материал или нужный раздел? Расскажите нам — поможем разобраться.</p>${newForm}</dialog>`;
  document.body.append(widget);
  const dialog = document.getElementById('support-dialog');
  const query = new URL(location.href).searchParams;
  const operator = query.get('mode') === 'staff';
  let enabled = localStorage.getItem('vesh-support-demo-permission') === 'true';
  let current;
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
      const id=await action('readwrite',store=>store.add({subject,category:values.get('category'),status:'new',created:now,origin:location.pathname,originTitle:document.title,messages:[{body,attachments,staff:false,created:now}]}));
      location.href=pageUrl('?ticket='+id+'&sent=1');
    }catch(error){form.querySelector('[data-demo-error]').textContent=error.message||'Не удалось сохранить. Разрешите хранение данных в браузере и повторите.';newSaving=false;button.disabled=false;button.textContent='Отправить обращение';}
  });
  const main=document.getElementById('support-demo-page');
  async function render() {
    if(!main)return;
    const records=(await action('readonly',store=>store.getAll())).sort((a,b)=>b.id-a.id);
    const selected=Number(query.get('ticket'));
    current=records.find(t=>t.id===selected);
    const toolbar=`<div class="support-panel" style="padding:14px 18px;margin-bottom:24px"><p class="support-hint">${demoNotice}</p><div class="support-actions" style="margin-top:8px"><a class="support-text-link" href="${pageUrl()}">Вид слушателя</a><a class="support-text-link" href="${pageUrl('?mode=staff')}">Вид сотрудника</a><label style="font-size:12px"><input type="checkbox" id="demo-support-permission" ${enabled?'checked':''}> Выдана функция техподдержки</label></div></div>`;
    if(operator&&!enabled){main.innerHTML=toolbar+`<div class="support-empty"><h1>Нет доступа к техподдержке</h1><p>Старший администратор должен включить сотруднику функцию «Обрабатывает обращения в техподдержку».</p><p class="support-hint">В демонстрации это можно проверить переключателем выше.</p></div>`;bind();return;}
    if(!current){
      main.innerHTML=toolbar+`<a class="support-back" href="${url('courses.html')}">← Моё обучение</a><div class="support-page-heading"><div><span class="support-eyebrow">ПОМОЩЬ В ЛИЧНОМ КАБИНЕТЕ</span><h1>${operator?'Техподдержка':'Мои обращения'}</h1><p>${operator?'Общая очередь технических обращений':'Здесь сохраняются ваши вопросы и ответы техподдержки.'}</p></div><button class="support-primary" id="demo-new-button">Новое обращение</button></div><div class="support-ticket-list">${records.length?records.map(t=>`<a class="support-ticket-card" href="${pageUrl('?ticket='+t.id+(operator?'&mode=staff':''))}"><div><span class="support-ticket-meta">№${t.id} — ${date(t.created)}</span><h2>${escape(t.subject)}</h2><span class="support-hint">${categories[t.category]}</span></div>${stamp(t)}</a>`).join(''):`<div class="support-empty"><span class="support-empty-icon" aria-hidden="true">?</span><h2>Пока нет обращений</h2><p>Если возникнут сложности с личным кабинетом,<br>напишите нам через кнопку «Помощь».</p></div>`}</div>`;
      bind();document.getElementById('demo-new-button').addEventListener('click',()=>dialog.showModal());return;
    }
    main.classList.add('support-page-narrow');
    main.innerHTML=toolbar+`<a class="support-back" href="${pageUrl(operator?'?mode=staff':'')}">← ${operator?'Очередь техподдержки':'Мои обращения'}</a>${query.has('sent')?'<div class="support-success" role="status">✓ Обращение сохранено в демонстрации. В службу поддержки оно не отправлено.</div>':''}<div class="support-thread-heading"><div class="support-thread-meta"><span>Обращение №${current.id}</span>${stamp(current)}</div><h1>${escape(current.subject)}</h1><p class="support-hint">${categories[current.category]} — ${date(current.created)}</p><p class="support-hint">Страница обращения: ${escape(current.originTitle)}</p></div>${operator?`<form id="demo-status-form" class="support-status-form"><label for="demo-status">Статус</label><select id="demo-status">${Object.entries(statuses).map(([key,title])=>`<option value="${key}" ${key===current.status?'selected':''}>${title}</option>`).join('')}</select><button class="support-secondary">Сохранить</button></form>`:''}<section class="support-messages" aria-label="Переписка по обращению">${current.messages.map((message,mi)=>`<article class="support-message ${message.staff?'support-message-staff':''}"><header><strong>${message.staff?(operator?'Техподдержка — Мария':'Техподдержка'):'Анна Петрова'}</strong><time>${date(message.created)}</time></header><p class="support-message-body">${escape(message.body)}</p><ul class="support-attached">${message.attachments.map((file,fi)=>`<li><a href="#" data-demo-download="${mi}:${fi}">↓ ${escape(file.name)}</a><span>${Math.ceil(file.size/1024)} КБ</span></li>`).join('')}</ul></article>`).join('')}</section><section class="support-panel"><h2>${operator?'Ответить слушателю':'Добавить сообщение'}</h2>${current.status==='resolved'&&!operator?'<p class="support-hint">Если проблема осталась, напишите здесь — мы снова откроем обращение.</p>':''}<form class="support-form" id="demo-reply-form"><label for="demo-reply">Сообщение</label><textarea id="demo-reply" name="body" rows="4" maxlength="5000" required></textarea>${files('demo-reply')}<p data-demo-error role="alert"></p><div class="support-actions"><button class="support-primary">Отправить сообщение</button></div></form></section>`;
    bind();
    main.querySelectorAll('[data-demo-download]').forEach(link=>link.addEventListener('click',(event)=>{event.preventDefault();const [mi,fi]=link.dataset.demoDownload.split(':').map(Number);const file=current.messages[mi].attachments[fi];const href=URL.createObjectURL(file);const a=document.createElement('a');a.href=href;a.download=file.name;a.click();setTimeout(()=>URL.revokeObjectURL(href),1000);}));
    document.getElementById('demo-status-form')?.addEventListener('submit',async(event)=>{event.preventDefault();current.status=document.getElementById('demo-status').value;await action('readwrite',store=>store.put(current));await render();});
    let replying=false;
    document.getElementById('demo-reply-form').addEventListener('submit',async(event)=>{
      event.preventDefault();if(replying)return;const form=event.currentTarget;
      try{const attachments=validateFiles(form);const body=form.elements.body.value.trim();if(!body)throw new Error('Напишите сообщение.');replying=true;form.querySelector('button').disabled=true;const next=structuredClone(current);next.messages.push({body,attachments,staff:operator,created:new Date().toISOString()});next.status=operator?'in_progress':'new';await action('readwrite',store=>store.put(next));await render();}catch(error){form.querySelector('[data-demo-error]').textContent=error.message;form.querySelector('button').disabled=false;replying=false;}
    });
  }
  function bind(){document.getElementById('demo-support-permission')?.addEventListener('change',event=>{enabled=event.target.checked;localStorage.setItem('vesh-support-demo-permission',String(enabled));render().catch(showError);});}
  function showError(){if(main)main.innerHTML='<div class="support-errors" role="alert">Не удалось открыть демонстрацию. Разрешите сохранение данных в браузере и обновите страницу.</div>';}
  render().catch(showError);
  const shared=document.createElement('script');shared.src=url('support.js?v=20261127-support-1');shared.onload=()=>{if(query.has('new'))dialog.showModal();};document.body.append(shared);
})();
