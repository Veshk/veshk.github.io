'use strict';
(() => {
  const key = 'vesh-portal-demo-v1';
  const initial = () => ({requests:[{id:1,name:'Илья Морозов',email:'waiting@vesh.test',group:'mba',status:'pending',message:'Учусь в ноябрьской группе. Договор оформлен.',mail:false},{id:2,name:'Наталья Соколова',email:'unknown@vesh.test',group:'',status:'pending',message:'Поступила на программу по управлению. Не помню номер группы.',mail:false},{id:3,name:'Олег Крылов',email:'other@vesh.test',group:'excel',status:'pending',message:'Корпоративная группа, онлайн.',mail:false}],reviewers:['admin','senior'],days:30});
  let state = initial();
  try { const saved = JSON.parse(localStorage.getItem(key)); if(saved && Array.isArray(saved.requests) && saved.requests.length===3 && Array.isArray(saved.reviewers)) state=saved; } catch {}
  const save = () => { try { localStorage.setItem(key, JSON.stringify(state)); } catch { notice('Изменение применено на этой странице. Браузер не разрешил сохранить его между страницами.'); } };
  const notice = text => { const el=document.getElementById('demo-notice'); if(el) el.textContent=text; };
  const escape = value => String(value ?? '').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const groups = {mba:{name:'MBA Коммерческий директор',code:'КД · 2026/11',owner:'Мария Белова',id:'admin'},excel:{name:'Excel для эффективной работы',code:'EXCEL · 2026/11',owner:'Ольга Орлова',id:'colleague'}};
  const senior = new URLSearchParams(location.search).get('role')==='senior';
  const role = senior?'senior':'admin';
  const canReview = row => row.group ? senior || groups[row.group]?.id===role : state.reviewers.includes(role);
  const labels={pending:'На проверке',approved:'Доступ открыт',rejected:'Отклонена'};
  const badges={pending:'wait',approved:'',rejected:'rejected'};
  document.querySelector('[data-reset]')?.addEventListener('click',()=>{state=initial();save();notice('Демонстрация сброшена. Все три заявки снова ожидают проверки.');});
  document.querySelectorAll('[data-demo-form]').forEach(form=>{
    form.querySelectorAll('textarea').forEach(el=>{el.value='Демонстрационная заявка. Нужна помощь с зачислением.';el.readOnly=true;});
    if(form.dataset.demoForm==='register.html') form.querySelector('select').value='1';
    form.addEventListener('submit',event=>{event.preventDefault(); const id=form.querySelector('select')?.value;const row=state.requests[0];row.group=id==='1'?'mba':id==='2'?'excel':'';row.status='pending';row.mail=false;save();location.href='waiting.html';});
  });
  if(location.pathname.endsWith('waiting.html')){
    const row=state.requests[0];
    document.querySelectorAll('header a[href="courses.html"]').forEach(a=>a.href='waiting.html');
    const box=document.querySelector('.waiting-card');
    if(row.status==='approved') box.innerHTML=`<div><span class="badge">● Доступ открыт</span><h2>Можно начинать обучение</h2><p>Администратор одобрил вашу демонстрационную заявку. Материалы группы доступны.</p><a class="btn" style="margin-top:20px" href="${row.group==='mba'?'course.html?student=waiting':'excel.html?student=waiting'}">Перейти к обучению ↗</a></div><div><h3>${escape(groups[row.group]?.name)}</h3><p style="margin-top:12px">${row.mail?'Подготовлен пример письма об открытии доступа.':'Доступ открыт без письма.'} Реальные сообщения не отправлялись.</p></div>`;
    if(row.status==='rejected') box.innerHTML='<div><span class="badge rejected">Заявка отклонена</span><h2>Уточним вашу группу</h2><p>В демонстрации администратор отклонил заявку. Вы можете выбрать другую группу и подать её повторно.</p><a class="btn" style="margin-top:20px" href="register.html">Повторить заявку ↗</a></div>';
    const request=document.querySelector('.request-row');
    if(request){ request.querySelector('h3').textContent=groups[row.group]?.name || 'Помощь с выбором группы';request.querySelector('small').textContent=groups[row.group]?.code || 'Обращение передано ответственным сотрудникам';const badge=request.querySelector('.badge');badge.className='badge '+badges[row.status];badge.textContent=labels[row.status]; }
  }
  if(new URLSearchParams(location.search).get('student')==='waiting'){ document.querySelectorAll('.person .name').forEach(el=>el.textContent='Илья Морозов'); document.querySelectorAll('.person .avatar').forEach(el=>el.textContent='И'); document.querySelectorAll('a[href="courses.html"]').forEach(el=>el.href='waiting.html'); }
  if(!document.getElementById('requests-body')) return;
  if(senior){ document.getElementById('admin-role').textContent='Старший администратор · Елена Волкова';document.getElementById('reviewers-tab').hidden=false; }
  const dialog=document.getElementById('action-dialog');let action=null;let active=null;
  const close=()=>dialog.close();
  dialog.querySelectorAll('[data-close],.dialog-close').forEach(b=>b.addEventListener('click',close));
  const render=()=>{
    const search=document.getElementById('search').value.toLocaleLowerCase();const filter=document.getElementById('filter').value;
    document.getElementById('pending-count').textContent=state.requests.filter(r=>r.status==='pending').length;
    const rows=state.requests.filter(r=>(r.name+' '+(groups[r.group]?.name||'')).toLocaleLowerCase().includes(search)&&(filter==='all'||r.status===filter));
    document.getElementById('requests-body').innerHTML=rows.map(r=>`<tr><td><strong>${escape(r.name)}</strong><small>${escape(r.email)}</small></td><td><strong>${escape(groups[r.group]?.name||'Группа не указана')}</strong><small>${escape(groups[r.group]?.code||'Нужна помощь ответственного')}</small>${!canReview(r)?'<span class="read-only">Только просмотр</span>':''}</td><td><span class="badge ${badges[r.status]}">${labels[r.status]}</span></td><td><div class="row-actions"><button data-action="details" data-id="${r.id}">Подробнее</button>${r.status==='pending'&&canReview(r)?`${r.group?`<button data-action="approve" data-id="${r.id}">Открыть доступ</button>`:''}<button data-action="assign" data-id="${r.id}">Назначить группу</button><button class="danger" data-action="reject" data-id="${r.id}">Отклонить</button>`:''}${r.mail?`<button data-action="mail" data-id="${r.id}">Пример письма</button>`:''}</div></td></tr>`).join('')||'<tr><td colspan="4">Заявок с такими условиями нет.</td></tr>';
    document.getElementById('groups-list').innerHTML=Object.entries(groups).map(([id,g])=>`<article class="card group-card"><span class="badge">Приём заявок открыт</span><h2>${g.name}</h2><div class="eyebrow muted">${g.code}</div><dl><dt>Администратор</dt><dd>${g.owner}</dd><dt>Срок после выпуска</dt><dd>${id==='mba'?state.days:30} дней</dd><dt>Права</dt><dd>${senior||g.id===role?'Можно редактировать':'Только просмотр'}</dd></dl>${id==='mba'?'<a class="btn outline" href="course.html">Посмотреть страницу ↗</a>':''}${(senior||g.id===role)&&id==='mba'?'<button class="text-button" style="margin:16px" data-days>Изменить срок</button>':''}</article>`).join('');
    document.querySelectorAll('[data-action]').forEach(b=>b.addEventListener('click',()=>open(b.dataset.action,Number(b.dataset.id))));
    document.querySelector('[data-days]')?.addEventListener('click',()=>open('days',null));
  };
  const open=(type,id)=>{
    active=state.requests.find(r=>r.id===id);action=type;
    const body=document.getElementById('dialog-content');const title=document.getElementById('dialog-title');const confirm=document.getElementById('dialog-confirm');confirm.hidden=false;
    const name=escape(active?.name);
    if(type==='approve'){title.textContent='Открыть доступ к группе';body.innerHTML=`<p>${name} · ${escape(groups[active.group].name)}</p><label class="check"><input id="send-email" type="checkbox" checked>Подготовить письмо «Доступ открыт»</label><p class="form-note" style="margin-top:15px">Если снять галочку, доступ откроется без письма. Здесь сохраняется только пример, отправки нет.</p>`;confirm.textContent='Открыть доступ';}
    if(type==='assign'){title.textContent='Назначить учебную группу';body.innerHTML=`<p>${name}. После назначения заявку проверит администратор выбранной группы.</p><label class="field">Группа<select id="target-group">${Object.entries(groups).map(([k,g])=>`<option value="${k}"${k===active.group?' selected':''}>${g.name}</option>`).join('')}</select></label>`;confirm.textContent='Назначить группу';}
    if(type==='reject'){title.textContent='Отклонить заявку';body.innerHTML=`<p>${name}. Материалы группы останутся недоступны. В демонстрации можно подать заявку повторно.</p>`;confirm.textContent='Отклонить заявку';}
    if(type==='details'){title.textContent=active.name;body.innerHTML=`<p>${escape(active.email)}</p><p>${escape(active.message)}</p><p>Статус: ${labels[active.status]}</p><p>${canReview(active)?'Заявка доступна для обработки.':'Чужая группа: только просмотр.'}</p>`;confirm.hidden=true;}
    if(type==='mail'){title.textContent='Предпросмотр письма';body.innerHTML=`<p>Пример для ${escape(active.email)} · без отправки</p><div class="mail-preview"><h3>Доступ к обучению открыт</h3><p>${name}, здравствуйте!</p><p>В кабинете доступна программа «${escape(groups[active.group].name)}».</p><a class="btn" href="${active.group==='mba'?'course.html':'excel.html'}?student=waiting">Перейти в кабинет ↗</a></div>`;confirm.hidden=true;}
    if(type==='days'){title.textContent='Срок доступа после выпуска';body.innerHTML=`<label class="field">Количество дней<input id="access-days" type="number" min="0" max="3650" required value="${state.days}"></label><p class="form-note">Общее правило для всей демонстрационной группы.</p>`;confirm.textContent='Сохранить срок';}
    dialog.showModal();
  };
  document.getElementById('action-form').addEventListener('submit',event=>{
    event.preventDefault();
    if(active&&['approve','assign','reject'].includes(action)&&(!canReview(active)||active.status!=='pending')) return;
    if(action==='approve'){active.status='approved';active.mail=document.getElementById('send-email').checked;notice('Демонстрационный доступ открыт'+(active.mail?'. Пример письма подготовлен.': ' без письма.')+' Посмотрите кабинет слушателя.');}
    if(action==='assign'){active.group=document.getElementById('target-group').value;notice('Группа назначена. Доступ откроется после одобрения ответственным администратором.');}
    if(action==='reject'){active.status='rejected';notice('Демонстрационная заявка отклонена.');}
    if(action==='days'){state.days=Number(document.getElementById('access-days').value);notice('Срок демонстрационной группы обновлён.');}
    save();close();render();
  });
  document.getElementById('search').addEventListener('input',render);document.getElementById('filter').addEventListener('change',render);
  document.querySelectorAll('[data-panel]').forEach(b=>b.addEventListener('click',()=>{if(b.dataset.panel==='reviewers'&&!senior)return;document.querySelectorAll('[data-panel]').forEach(x=>x.classList.toggle('active',x===b));['requests','groups','reviewers'].forEach(p=>document.getElementById(p+'-panel').hidden=p!==b.dataset.panel);}));
  document.querySelectorAll('[name=reviewer]').forEach(input=>input.checked=state.reviewers.includes(input.value));
  document.getElementById('reviewers-form').addEventListener('submit',event=>{event.preventDefault();if(!senior)return;state.reviewers=Array.from(document.querySelectorAll('[name=reviewer]:checked')).map(x=>x.value);save();render();notice('Ответственные сотрудники выбраны для этой демонстрации.');});
  render();
})();
