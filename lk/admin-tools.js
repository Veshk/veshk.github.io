'use strict';
window.AdminTools = (() => {
  const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const avatar=(person,size='')=>person?.photo?`<img class="staff-photo ${size}" src="${esc(person.photo)}" alt="Фото администратора">`:`<span class="avatar staff-photo ${size}">${esc(person?.name?.slice(0,1)||'А')}</span>`;
  const init=ctx=>{
    ctx.state.staffProfiles??={};
    const defaults={admin:'Мария Белова',colleague:'Ольга Орлова',senior:'Елена Волкова'};
    const profiles=Object.fromEntries(Object.entries(defaults).map(([id,name])=>[id,{name,text:'Поможет с расписанием и организационными вопросами.',contact_url:'',photo:'',...ctx.state.staffProfiles[id]}]));
    Object.values(ctx.groups).forEach(g=>{g.staffProfile=profiles[g.id];g.owner=profiles[g.id]?.name||g.owner;});
    const editor=location.pathname.endsWith('editor.html'),admin=location.pathname.endsWith('admin.html');
    const nav=document.querySelector('.top-nav');
    if(nav&&!nav.querySelector('[data-admin-link]'))nav.insertAdjacentHTML('beforeend',`<a class="admin-entry" data-admin-link href="admin.html?role=${ctx.role}&panel=groups">Панель администратора</a>`);
    if(editor||admin){const name=document.querySelector('.person .name');if(name)name.textContent=profiles[ctx.role]?.name||defaults.admin;const av=document.querySelector('.person .avatar');if(av)av.textContent=(profiles[ctx.role]?.name||defaults.admin).slice(0,1);}
    if(!admin)return;
    document.getElementById('admin-role').textContent=(ctx.senior?'Старший администратор — ':'Администратор — ')+profiles[ctx.role].name;
    document.querySelector('.admin-tabs').insertAdjacentHTML('beforeend','<button data-panel="profiles">Администраторы и фото</button>');
    const host=document.createElement('section');host.id='profiles-panel';host.hidden=true;document.querySelector('#demo-notice').before(host);
    let selected=ctx.role,pendingPhoto='';
    const draw=()=>{
      const p=profiles[selected],can=ctx.senior||selected===ctx.role;pendingPhoto=p.photo||'';
      host.innerHTML=`<div class="profile-intro"><h2>Фото и контакты администратора</h2><p>Сохраняются в профиле и показываются во всех группах этого сотрудника. Старший администратор может менять любой профиль.</p></div><form id="staff-profile-form" class="card staff-profile-form"><label class="field">Сотрудник<select id="staff-person">${Object.entries(profiles).map(([id,p])=>`<option value="${id}" ${id===selected?'selected':''}>${esc(p.name)}</option>`).join('')}</select></label><div class="profile-photo-row"><div id="staff-avatar">${avatar(p,'large')}</div><div><label class="field">Фотография администратора<input id="staff-photo-file" type="file" accept="image/jpeg,image/png,image/webp" ${can?'':'disabled'}></label><p class="form-note">JPG, PNG или WebP, до 2 МБ. Для демонстрации используйте тестовое изображение.</p><button type="button" class="text-button" id="staff-remove-photo" ${can?'':'disabled'}>Убрать фото</button></div></div><label class="field">Контактная информация<textarea name="text" rows="3" maxlength="2000" ${can?'':'readonly'}>${esc(p.text)}</textarea></label><label class="field">Ссылка для связи<input name="contact_url" type="url" placeholder="https://…" value="${esc(p.contact_url)}" ${can?'':'readonly'}></label><p id="profile-status" class="form-note" role="status">${can?'Изменения появятся в контактных блоках после сохранения.':'Только просмотр. Свой профиль можно выбрать выше.'}</p>${can?'<button class="btn" type="submit">Сохранить профиль</button>':''}</form>`;
      host.querySelector('#staff-person').addEventListener('change',e=>{selected=e.target.value;draw();});
      const status=host.querySelector('#profile-status'),photo=host.querySelector('#staff-photo-file'),save=host.querySelector('[type=submit]');
      photo.addEventListener('change',async()=>{
        if(!can||!photo.files[0])return;const file=photo.files[0];
        if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size>2*1024*1024){status.textContent='Выберите JPG, PNG или WebP размером до 2 МБ.';photo.value='';return;}
        save.disabled=true;
        try{const bitmap=await createImageBitmap(file);const canvas=document.createElement('canvas');const scale=Math.min(1,600/Math.max(bitmap.width,bitmap.height));canvas.width=Math.round(bitmap.width*scale);canvas.height=Math.round(bitmap.height*scale);canvas.getContext('2d').drawImage(bitmap,0,0,canvas.width,canvas.height);bitmap.close();pendingPhoto=canvas.toDataURL('image/jpeg',.85);host.querySelector('#staff-avatar').innerHTML=avatar({...p,photo:pendingPhoto},'large');status.textContent='Фото загружено. Нажмите «Сохранить профиль».';}catch{status.textContent='Не удалось прочитать изображение.';}finally{save.disabled=false;}
      });
      host.querySelector('#staff-remove-photo').addEventListener('click',()=>{if(!can)return;pendingPhoto='';host.querySelector('#staff-avatar').innerHTML=avatar({...p,photo:''},'large');status.textContent='Фото будет убрано после сохранения.';});
      host.querySelector('form').addEventListener('submit',e=>{e.preventDefault();if(!can)return;const data=new FormData(e.target),contact=String(data.get('contact_url')).trim();if(contact&&!/^https?:\/\//i.test(contact)){status.textContent='Укажите ссылку http:// или https://.';return;}profiles[selected]={...p,text:String(data.get('text')).trim(),contact_url:contact,photo:pendingPhoto};ctx.state.staffProfiles[selected]=profiles[selected];status.textContent=ctx.save()?'Профиль сохранён. Фото и контакты обновятся в группах.':'Не удалось сохранить. Попробуйте изображение меньшего размера.';});
    };draw();
  };
  return {init,avatar};
})();
