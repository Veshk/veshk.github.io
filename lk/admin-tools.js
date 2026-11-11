'use strict';
window.AdminTools = (() => {
  const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const photoPosition=p=>Math.max(0,Math.min(100,Number(p?.photo_position)||0));
  const avatar=(person,size='')=>person?.photo?`<img class="staff-photo ${size}" src="${esc(person.photo)}" alt="Фото администратора" style="object-position:50% ${photoPosition(person)}%">`:`<span class="avatar staff-photo ${size}">${esc(person?.name?.slice(0,1)||'А')}</span>`;
  const init=ctx=>{
    ctx.state.staffProfiles??={};
    const profiles=Object.fromEntries(Object.entries(ctx.staff).map(([id,p])=>[id,{...p,...ctx.state.staffProfiles[id],name:p.name,email:p.email}]));
    const sync=()=>Object.values(ctx.groups).forEach(g=>{g.staffProfile=profiles[g.id];g.owner=profiles[g.id]?.name||g.owner;});sync();
    const editor=location.pathname.endsWith('editor.html'),admin=location.pathname.endsWith('admin.html');
    const nav=document.querySelector('.top-nav');
    if(nav&&!nav.querySelector('[data-admin-link]'))nav.insertAdjacentHTML('beforeend',`<a class="admin-entry" data-admin-link href="admin.html?role=${ctx.role}&panel=groups">Панель администратора</a>`);
    if(editor||admin){const name=document.querySelector('.person .name');if(name)name.textContent=profiles[ctx.role]?.name;const av=document.querySelector('.person .avatar');if(av)av.textContent=profiles[ctx.role]?.name.slice(0,1);}
    if(!admin)return;
    document.getElementById('admin-role').textContent=(ctx.senior?'Старший администратор — ':'Администратор — ')+profiles[ctx.role].name;
    document.querySelector('.admin-tabs').insertAdjacentHTML('beforeend','<button data-panel="profiles">Администраторы</button>');
    const host=document.createElement('section');host.id='profiles-panel';host.hidden=true;document.querySelector('#demo-notice').before(host);
    host.innerHTML=`<div class="group-workspace"><div class="group-filters directory-filters"><label class="field">Поиск администратора<input id="staff-search" type="search" placeholder="Имя или почта аккаунта"></label><label class="field">Группы<select id="staff-groups-filter"><option value="">Все сотрудники</option><option value="with">С группами</option><option value="without">Без групп</option></select></label><label class="field">Дополнительная функция<select id="staff-review-filter"><option value="">Все сотрудники</option><option value="yes">Разбирает заявки без группы</option><option value="no">Не назначена</option></select></label></div><div class="group-toolbar"><span>Один сотрудник — один аккаунт и профиль</span><span id="staff-count" role="status"></span></div><div class="group-grid-scroll" tabindex="0" role="region" aria-label="Таблица администраторов"><table class="group-grid"><thead><tr><th><button id="staff-sort">Администратор <span>↕</span></button></th><th>Роль аккаунта</th><th>Группы</th><th>Заявки без группы</th><th>Контакты</th><th>Действия</th></tr></thead><tbody id="staff-rows"></tbody></table></div></div><p class="group-footnote">Имя открывает профиль. Число групп — переход к списку. Дополнительные права назначает старший администратор.</p>`;
    const dialog=document.createElement('dialog');dialog.id='staff-dialog';dialog.setAttribute('aria-labelledby','staff-dialog-title');document.body.append(dialog);
    let direction=1;
    const count=id=>Object.values(ctx.groups).filter(g=>g.id===id).length;
    const render=()=>{
      const search=host.querySelector('#staff-search').value.trim().toLocaleLowerCase('ru'),filter=host.querySelector('#staff-groups-filter').value,review=host.querySelector('#staff-review-filter').value;
      const rows=Object.entries(profiles).filter(([id,p])=>(p.name+' '+p.email).toLocaleLowerCase('ru').includes(search)&&(!filter||(filter==='with'?count(id)>0:count(id)===0))&&(!review||(ctx.state.reviewers.includes(id)===(review==='yes')))).sort((a,b)=>a[1].name.localeCompare(b[1].name,'ru')*direction);
      host.querySelector('#staff-count').textContent=`Показано ${rows.length} из ${Object.keys(profiles).length}`;
      host.querySelector('#staff-sort').closest('th').setAttribute('aria-sort',direction===1?'ascending':'descending');
      host.querySelector('#staff-rows').innerHTML=rows.map(([id,p])=>`<tr class="group-data-row"><td><div class="staff-cell">${avatar(p)}<div><button class="group-name" data-staff-open="${esc(id)}">${esc(p.name)}</button><span class="group-subtitle">${esc(p.email)}</span></div></div></td><td><span class="group-status status-${p.role==='senior'?'purple':'gray'}">${p.role==='senior'?'Старший администратор':'Администратор'}</span></td><td><button class="group-action" data-staff-groups="${esc(id)}">${count(id)?count(id)+' К группам →':'Нет групп →'}</button></td><td>${ctx.state.reviewers.includes(id)?'<span class="group-status status-blue">Назначен ответственным</span>':'<span class="group-unset">Не назначен</span>'}</td><td>${p.contact_url?'<span class="group-status status-green">Заполнены</span>':'<span class="group-unset">Не заполнены</span>'}</td><td><button class="group-action" data-staff-open="${esc(id)}">${ctx.senior||id===ctx.role?'Настроить':'Профиль'}</button></td></tr>`).join('')||'<tr><td colspan="6" class="group-empty">Администраторы не найдены. Измените фильтры.</td></tr>';
    };
    const open=id=>{
      const p=profiles[id],can=ctx.senior||id===ctx.role;let pendingPhoto=p.photo||'',pendingPosition=photoPosition(p);
      dialog.innerHTML=`<form id="staff-profile-form"><button type="button" class="dialog-close" aria-label="Закрыть">×</button><h2 id="staff-dialog-title">${esc(p.name)}</h2><div class="staff-account"><span class="group-status status-gray">${p.role==='senior'?'Старший администратор':'Администратор'}</span><p>${esc(p.email)}</p><span class="form-note">Почта аккаунта и роль в этой демонстрации — тестовые.</span><button type="button" class="group-action" data-staff-groups="${esc(id)}">Группы: ${count(id)} →</button></div><h3>Фото и контакты</h3><p class="form-note">Показываются во всех группах сотрудника.</p><div class="profile-photo-row"><div id="staff-avatar">${avatar(p,'large')}</div><div><label class="field">Фотография<input id="staff-photo-file" type="file" accept="image/jpeg,image/png,image/webp" ${can?'':'disabled'}></label><p class="form-note">JPG, PNG или WebP, до 2 МБ.</p><button type="button" class="text-button" id="staff-remove-photo" ${can?'':'disabled'}>Убрать фото</button></div></div><label class="field">Положение фото по вертикали<input type="range" id="staff-photo-position" min="0" max="100" step="1" value="${pendingPosition}" ${can?'':'disabled'}></label><p class="form-note">Перемещайте ползунок, чтобы лицо оказалось по центру круга.</p><label class="field">Контактная информация<textarea name="text" rows="3" maxlength="2000" ${can?'':'readonly'}>${esc(p.text)}</textarea></label><label class="field">Ссылка для связи<input name="contact_url" type="url" value="${esc(p.contact_url)}" placeholder="https://…" ${can?'':'readonly'}></label><div class="staff-permissions"><h3>Дополнительная функция</h3><label class="check"><input name="reviews_unassigned" type="checkbox" ${ctx.state.reviewers.includes(id)?'checked':''} ${ctx.senior?'':'disabled'}>Разбирает заявки без группы</label><p class="form-note">Может помочь слушателю выбрать группу. Не даёт право изменять чужие группы.${ctx.senior?'':' Настройку меняет старший администратор.'}</p></div><p id="profile-status" class="form-note" role="status">${can?'':'Профиль доступен только для просмотра.'}</p><div class="dialog-actions"><button type="button" class="btn outline" data-staff-close>Закрыть</button>${can?'<button class="btn" type="submit">Сохранить</button>':''}</div></form>`;
      dialog.querySelectorAll('.dialog-close,[data-staff-close]').forEach(b=>b.onclick=()=>dialog.close());
      dialog.querySelector('[data-staff-groups]').onclick=()=>{dialog.close();ctx.openGroups(id);};
      const status=dialog.querySelector('#profile-status'),photo=dialog.querySelector('#staff-photo-file'),save=dialog.querySelector('[type=submit]');
      dialog.querySelector('#staff-photo-position').oninput=e=>{pendingPosition=photoPosition({photo_position:e.target.value});const img=dialog.querySelector('#staff-avatar img');if(img)img.style.objectPosition=`50% ${pendingPosition}%`;};
      photo.onchange=async()=>{
        if(!can||!photo.files[0])return;const file=photo.files[0];
        if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size>2*1024*1024){status.textContent='Выберите JPG, PNG или WebP до 2 МБ.';photo.value='';return;}
        save.disabled=true;
        try{const bitmap=await createImageBitmap(file);const canvas=document.createElement('canvas'),scale=Math.min(1,600/Math.max(bitmap.width,bitmap.height));canvas.width=Math.round(bitmap.width*scale);canvas.height=Math.round(bitmap.height*scale);canvas.getContext('2d').drawImage(bitmap,0,0,canvas.width,canvas.height);bitmap.close();pendingPhoto=canvas.toDataURL('image/jpeg',.85);dialog.querySelector('#staff-avatar').innerHTML=avatar({...p,photo:pendingPhoto,photo_position:pendingPosition},'large');status.textContent='Фото готово к сохранению.';}catch{status.textContent='Не удалось прочитать изображение.';}finally{save.disabled=false;}
      };
      dialog.querySelector('#staff-remove-photo').onclick=()=>{if(!can)return;pendingPhoto='';dialog.querySelector('#staff-avatar').innerHTML=avatar({...p,photo:''},'large');};
      dialog.querySelector('form').onsubmit=e=>{
        e.preventDefault();if(!can||save.disabled)return;const data=new FormData(e.target),contact=String(data.get('contact_url')).trim();
        if(contact&&!/^https?:\/\//i.test(contact)){status.textContent='Укажите ссылку http:// или https://.';return;}
        profiles[id]={...p,text:String(data.get('text')).trim(),contact_url:contact,photo:pendingPhoto,photo_position:pendingPhoto?pendingPosition:0};ctx.state.staffProfiles[id]=profiles[id];
        if(ctx.senior)ctx.state.reviewers=[...ctx.state.reviewers.filter(x=>x!==id),...(data.has('reviews_unassigned')?[id]:[])];
        sync();const saved=ctx.save();render();document.dispatchEvent(new Event('staff-profile-saved'));
        status.textContent=saved?'Профиль и настройки сохранены.':'Изменения применены здесь, но браузер не смог их сохранить.';
      };
      dialog.showModal();
    };
    host.addEventListener('click',e=>{const b=e.target.closest('button');if(!b)return;if(b.dataset.staffOpen)open(b.dataset.staffOpen);if(b.dataset.staffGroups)ctx.openGroups(b.dataset.staffGroups);});
    host.querySelector('#staff-sort').onclick=()=>{direction*=-1;render();};
    host.querySelector('#staff-search').oninput=render;['staff-groups-filter','staff-review-filter'].forEach(id=>host.querySelector('#'+id).onchange=render);render();
    return {render};
  };
  return {init,avatar};
})();
