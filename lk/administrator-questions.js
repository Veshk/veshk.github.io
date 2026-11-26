'use strict';
window.AdministratorQuestions = {init(ctx) {
  const key='vesh-administrator-questions-demo-v1';
  const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const read=()=>{try{return JSON.parse(localStorage.getItem(key)||'[]');}catch{return [];}};
  let host;
  const render=()=>{
    if(!host)return;
    const rows=read().filter(r=>ctx.senior||ctx.groups[r.group]?.id===ctx.role).reverse();
    host.querySelector('tbody').innerHTML=rows.map(r=>`<tr><td>${esc(new Intl.DateTimeFormat('ru-RU',{dateStyle:'short',timeStyle:'short',timeZone:'Asia/Yekaterinburg'}).format(new Date(r.created_at)))}</td><td>Демо-слушатель</td><td>${esc(ctx.groups[r.group]?.code||r.group)}</td><td>${esc(r.administrator)}</td><td style="white-space:pre-wrap;overflow-wrap:anywhere">${esc(r.question)}</td><td>${r.handled?'Обработан':`<button class="btn outline" data-handle-question="${esc(r.id)}">Отметить обработанным</button>`}</td></tr>`).join('')||'<tr><td colspan="6">Вопросов пока нет</td></tr>';
  };
  if(location.pathname.endsWith('admin.html')){
    document.querySelector('.admin-tabs').insertAdjacentHTML('beforeend','<button data-panel="administratorQuestions">Вопросы администраторам</button>');
    host=document.createElement('section');host.id='administratorQuestions-panel';host.hidden=true;
    host.innerHTML='<p>Прототип — здесь только вопросы, сохранённые в этом браузере</p><div class="group-grid-scroll"><table class="group-grid"><thead><tr><th>Отправлен</th><th>Слушатель</th><th>Группа</th><th>Администратор</th><th>Вопрос</th><th>Статус</th></tr></thead><tbody></tbody></table></div>';
    document.getElementById('demo-notice').before(host);
    host.addEventListener('click',e=>{const button=e.target.closest('[data-handle-question]');if(!button)return;const rows=read();const row=rows.find(r=>r.id===button.dataset.handleQuestion);if(!row||!ctx.canEditGroup(row.group))return;row.handled=true;localStorage.setItem(key,JSON.stringify(rows));render();});
    render();
  }
  document.addEventListener('click',event=>{
    const trigger=event.target.closest('[data-administrator-open]');if(!trigger)return;
    const group=ctx.groups[ctx.groupId];if(!group)return;
    const query=new URLSearchParams(location.search),readonly=query.has('preview')||ctx.studentView||location.pathname.endsWith('editor.html');
    const dialog=document.createElement('dialog');dialog.className='advisor-question-dialog';dialog.setAttribute('aria-labelledby','administrator-question-title');
    dialog.innerHTML=`<button class="question-close" type="button" aria-label="Закрыть" data-close>×</button><h2 id="administrator-question-title">Вопрос администратору</h2><p>${esc(group.owner)}</p><form><label for="administrator-question-text">Ваш вопрос</label><textarea id="administrator-question-text" name="question" required maxlength="5000"${readonly?' disabled':''}></textarea><p class="advisor-question-hint">${readonly?'Это просмотр как студент. Отправка недоступна':'Прототип — вопрос сохраняется только в этом браузере'}</p><p data-error role="alert"></p><div class="advisor-question-actions"><button class="btn" type="submit"${readonly?' disabled':''}>Отправить вопрос</button><button class="btn outline" type="button" data-close>Отмена</button></div></form>`;
    document.body.append(dialog);dialog.querySelectorAll('[data-close]').forEach(b=>b.onclick=()=>dialog.close());dialog.addEventListener('close',()=>{dialog.remove();trigger.focus();});
    const form=dialog.querySelector('form'),field=form.querySelector('textarea');
    field.addEventListener('input',()=>field.setCustomValidity(''));
    form.addEventListener('submit',e=>{e.preventDefault();if(readonly)return;field.setCustomValidity(field.value.trim()?'':'Напишите ваш вопрос');if(!form.reportValidity())return;try{const rows=read();rows.push({id:crypto.randomUUID(),group:ctx.groupId,administrator:group.owner,question:field.value.trim(),created_at:new Date().toISOString(),handled:false});localStorage.setItem(key,JSON.stringify(rows));trigger.parentElement.querySelector('[data-administrator-status]').textContent='✓ Вопрос отправлен';dialog.close();}catch{form.querySelector('[data-error]').textContent='Не удалось сохранить вопрос. Текст остаётся в поле';}});
    dialog.showModal();if(!readonly)field.focus();
  });
  return {render};
}};
