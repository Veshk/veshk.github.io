'use strict';
window.AcademicAdvisors = (()=>{
  const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  function init(ctx){
    ctx.state.academicAdvisors??={};
    const tabs=document.querySelector('.admin-tabs');if(!tabs)return;
    tabs.insertAdjacentHTML('beforeend','<button data-panel="advisors">Научные руководители</button>');
    const section=document.createElement('section');section.id='advisors-panel';section.hidden=true;section.className='advisor-directory';
    section.innerHTML=`<div class="advisor-directory-header"><p>ФИО, фото и почта для карточки в ЛК. Выбор руководителя — в редакторе страницы группы.</p>${ctx.senior?'<button type="button" class="btn" data-advisor-create>Добавить руководителя</button>':'<p class="muted">Справочник меняет старший администратор.</p>'}</div><div class="advisor-directory-list"></div>`;
    document.querySelector('#demo-notice').before(section);
    const dialog=document.createElement('dialog');dialog.setAttribute('aria-labelledby','advisor-dialog-title');dialog.innerHTML=`<form class="advisor-form"><h2 id="advisor-dialog-title">Научный руководитель</h2><label class="field">ФИО<input name="name" required maxlength="255"></label><label class="field">Почта для связи<input name="email" type="email" maxlength="255"></label><label class="field">Ссылка на фото<input name="photo_url" type="url" maxlength="2000" placeholder="https://"></label><label class="field">Или загрузите фотографию<input name="photo_file" type="file" accept="image/jpeg,image/png,image/webp"></label><p class="form-note">JPG, PNG или WebP, до 2 МБ. Профиль используется во всех группах, где выбран этот руководитель.</p><img class="advisor-form-photo" alt="Предпросмотр фотографии" hidden><label class="check"><input name="remove_photo" type="checkbox">Убрать фото</label><p data-advisor-message role="status"></p><div class="dialog-actions"><button type="button" class="btn outline" data-advisor-close>Отмена</button><button type="submit" class="btn">Сохранить</button></div></form>`;document.body.append(dialog);
    const form=dialog.querySelector('form'),message=form.querySelector('[data-advisor-message]'),preview=form.querySelector('img');let selected=null,uploaded='',busy=false;
    const draw=()=>{section.querySelector('.advisor-directory-list').innerHTML=Object.entries(ctx.state.academicAdvisors).sort((a,b)=>a[1].name.localeCompare(b[1].name,'ru')).map(([id,p])=>{const photo=CourseOverview.safePhoto(p.photo);return `<article class="advisor-card"><div class="advisor-person">${photo?`<img src="${esc(photo)}" alt="${esc(p.name)}" referrerpolicy="no-referrer">`:'<span class="advisor-initials" aria-hidden="true">'+esc(p.name[0])+'</span>'}<div><h2>${esc(p.name)}</h2><p>${esc(p.email||'Почта не указана')}</p></div></div>${ctx.senior?`<button class="text-button" data-advisor-edit="${esc(id)}">Редактировать</button>`:''}</article>`;}).join('')||'<div class="empty">Научные руководители пока не добавлены.</div>';};
    const showPhoto=()=>{const value=form.elements.remove_photo.checked?'':uploaded||form.elements.photo_url.value;preview.src=CourseOverview.safePhoto(value);preview.hidden=!preview.getAttribute('src');};
    const open=id=>{if(!ctx.senior)return;selected=id;const p=ctx.state.academicAdvisors[id]||{};form.reset();form.elements.name.value=p.name||'';form.elements.email.value=p.email||'';uploaded=(p.photo||'').startsWith('data:')?p.photo:'';form.elements.photo_url.value=uploaded?'':p.photo||'';message.textContent='';showPhoto();dialog.showModal();};
    section.addEventListener('click',e=>{const button=e.target.closest('button');if(button?.hasAttribute('data-advisor-create'))open(null);else if(button?.dataset.advisorEdit)open(button.dataset.advisorEdit);});
    form.querySelector('[data-advisor-close]').onclick=()=>dialog.close();
    form.elements.photo_url.addEventListener('input',()=>{uploaded='';form.elements.remove_photo.checked=false;showPhoto();});
    form.elements.remove_photo.addEventListener('change',showPhoto);
    form.elements.photo_file.addEventListener('change',async()=>{
      const file=form.elements.photo_file.files[0];if(!file)return;busy=true;message.textContent='Проверяем фотографию…';
      try{
        if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size>2*1024*1024)throw Error('Нужен JPG, PNG или WebP размером до 2 МБ.');
        const data=await new Promise((resolve,reject)=>{const reader=new FileReader();reader.onload=()=>resolve(reader.result);reader.onerror=()=>reject(Error('Не удалось прочитать файл.'));reader.readAsDataURL(file);});
        const img=new Image();img.src=data;await img.decode();if(img.width>5000||img.height>5000)throw Error('Максимальный размер — 5000 × 5000 px.');
        uploaded=data;form.elements.photo_url.value='';form.elements.remove_photo.checked=false;showPhoto();message.textContent='Фотография готова. Сохраните профиль.';
      }catch(error){message.textContent=error.message;form.elements.photo_file.value='';}finally{busy=false;}
    });
    form.addEventListener('submit',e=>{
      e.preventDefault();if(!ctx.senior)return;if(busy){message.textContent='Дождитесь проверки фотографии.';return;}
      const name=form.elements.name.value.trim(),email=form.elements.email.value.trim(),photo=form.elements.remove_photo.checked?'':uploaded||form.elements.photo_url.value.trim();
      if(!name||email&&!CourseOverview.safeEmail(email)||photo&&!CourseOverview.safePhoto(photo)){message.textContent='Проверьте ФИО, почту и ссылку на фото (http или https).';return;}
      const id=selected||crypto.randomUUID(),previous=ctx.state.academicAdvisors[id];ctx.state.academicAdvisors[id]={name,email,photo};
      if(!ctx.save()){if(previous)ctx.state.academicAdvisors[id]=previous;else delete ctx.state.academicAdvisors[id];message.textContent='Не удалось сохранить. Уменьшите файл фотографии и попробуйте снова.';return;}
      draw();dialog.close();document.querySelector('#demo-notice').textContent='Профиль научного руководителя сохранён.';
    });
    document.addEventListener('academic-advisors-changed',draw);
    draw();return {render:draw};
  }
  return {init};
})();
