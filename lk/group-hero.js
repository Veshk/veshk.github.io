'use strict';
window.GroupHero = (()=>{
  const defaultText='Расписание, записи и материалы вашей группы собраны здесь. По вопросам обучения обращайтесь к администратору.';
  const positions=['center','top','bottom'];
  const defaultCover=type=>'brand/group-placeholder-'+(['Программа проф. переподготовки','Программа повышения квалификации'].includes(type)?'long':'short')+'.png';
  const safeImage=value=>/^data:image\/(jpeg|png|webp);base64,[A-Za-z0-9+/=]+$/.test(value||'')?value:'';
  function render(element,group){
    element.querySelector('.group-hero-background')?.remove();
    const customSource=safeImage(group.hero_image)||SchoolPrograms.safeCover(group.program_cover_url);
    const source=customSource||defaultCover(group.program_type);
    element.classList.toggle('has-default-cover',!customSource);
    element.classList.toggle('has-group-cover',!!source);
    if(source){const img=document.createElement('img');img.className='group-hero-background';img.alt='';img.src=source;img.style.objectPosition=positions.includes(group.hero_position)?group.hero_position:'center';element.prepend(img);}
    const text=element.querySelector('.hero-side p');if(text){text.textContent=group.description??defaultText;text.hidden=!text.textContent;}
  }
  function editor({root,record,allowed,mark,escape,programCover,programType}){
    let cover=record.hero_image||'',position=record.hero_position||'center',busy=false;
    const section=document.createElement('section');section.className='hero-editor editor-block';section.id='main-screen';
    section.innerHTML=`<h2>Главный экран</h2><p>Обложка и текст только этой группы.</p><div class="hero-editor-layout"><div><label class="field">Обложка группы<input type="file" data-hero-file accept="image/jpeg,image/png,image/webp"></label><p class="form-note">JPG, PNG или WebP, до 2 МБ. Рекомендуем <strong>2400 × 900 px</strong>, минимум 1200 × 450 px. Фото хорошего качества, без надписей; важные детали ближе к центру. На телефоне края обрезаются. Затемнение добавляется автоматически.</p><button type="button" class="text-button" data-hero-reset>Вернуть стандартную обложку</button><label class="field">Положение изображения<select data-hero-position><option value="center">По центру</option><option value="top">По верхнему краю</option><option value="bottom">По нижнему краю</option></select></label><label class="field">Текст на главном экране<textarea data-hero-text rows="4" maxlength="3000">${escape(record.description??defaultText)}</textarea></label><p class="form-note">Напишите приветствие именно для этой группы. Пустое поле скрывает текст.</p><p data-hero-status role="status"></p></div><div><div class="course-hero hero-mini-preview"><div class="eyebrow">${escape(record.code)}</div><h3>${escape(record.name)}</h3><div class="hero-side"><span class="badge">Доступ открыт</span><p></p></div></div><p class="form-note">Предпросмотр обложки. После сохранения откройте страницу ЛК, чтобы проверить её на компьютере и телефоне.</p></div></div>`;
    root.prepend(section);
    const file=section.querySelector('[data-hero-file]'),text=section.querySelector('[data-hero-text]'),select=section.querySelector('[data-hero-position]'),status=section.querySelector('[data-hero-status]');select.value=position;
    const draw=()=>render(section.querySelector('.course-hero'),{hero_image:cover,hero_position:position,description:text.value,program_cover_url:programCover,program_type:programType});draw();
    if(!allowed)section.querySelectorAll('input,textarea,button,select').forEach(el=>el.disabled=true);
    file.onchange=async()=>{const chosen=file.files[0];if(!chosen||!allowed)return;busy=true;status.textContent='Проверяем изображение…';try{
      if(!['image/jpeg','image/png','image/webp'].includes(chosen.type)||chosen.size>2*1024*1024)throw Error('Нужен JPG, PNG или WebP размером до 2 МБ.');
      const data=await new Promise((resolve,reject)=>{const reader=new FileReader();reader.onload=()=>resolve(reader.result);reader.onerror=()=>reject(Error('Не удалось прочитать файл.'));reader.readAsDataURL(chosen);});
      const img=new Image();img.src=data;await img.decode();
      if(img.width<1200||img.height<450||img.width>8000||img.height>8000)throw Error('Размер должен быть от 1200 × 450 до 8000 × 8000 px. Рекомендуем 2400 × 900 px.');
      cover=data;mark();draw();status.textContent=`${img.width} × ${img.height} px ${Math.round(chosen.size/1024)} КБ. Сохраните страницу.`;
    }catch(e){status.textContent=e.message;file.value='';}finally{busy=false;}};
    text.oninput=()=>{mark();draw();};select.onchange=()=>{position=select.value;mark();draw();};
    section.querySelector('[data-hero-reset]').onclick=()=>{cover='';file.value='';mark();draw();status.textContent='Стандартная обложка. Сохраните страницу.';};
    return {save(){if(busy){status.textContent='Дождитесь проверки изображения.';return false;}record.hero_image=cover;record.hero_position=position;record.description=text.value.trim();return true;}};
  }
  return {render,editor};
})();
