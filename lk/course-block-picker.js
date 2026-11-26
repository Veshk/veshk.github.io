'use strict';
window.CourseBlockPicker = (() => {
  const categories = [
    ['general', 'Общие блоки'], ['schedule', 'Расписание и занятия'],
    ['resources', 'Записи и материалы'], ['practice', 'Практика и задания'],
    ['coursework', 'Курсовая и защита'], ['graduation', 'Завершение обучения'],
  ];
  const details = {
    preparation: ['general', 'Шаги перед началом обучения и полезные ссылки'],
    contacts: ['general', 'Администратор группы, контакты и чаты'],
    info: ['general', 'Текст, пояснение и необязательная кнопка со ссылкой'],
    faq: ['general', 'Вопросы и ответы в раскрывающихся разделах'],
    video: ['general', 'Блок «Это ВЭШ» с видео о школе'],
    schedule: ['schedule', 'Даты, время, темы и преподаватели занятий'],
    consultations: ['schedule', 'Даты консультаций, условия участия и ссылки'],
    join: ['schedule', 'Ссылка и инструкция для подключения к занятию'],
    recordings: ['resources', 'Видеозаписи прошедших занятий'],
    recording_modules: ['resources', 'Записи занятий, распределённые по модулям'],
    materials: ['resources', 'Учебные файлы, папки, книги и дополнительные материалы'],
    homework: ['practice', 'Задания, сроки выполнения и ссылки'],
    introductions: ['practice', 'Практика «Знакомство» для участников группы'],
    coursework: ['coursework', 'Подготовка к курсовой, консультации и этапы защиты'],
    defense: ['coursework', 'Выбор даты и времени защиты, индивидуальная или парная запись'],
    photos: ['graduation', 'Фотографии выпускного и ссылка на альбом'],
    graduation: ['graduation', 'Поздравление с окончанием обучения и ссылка на сертификаты'],
    alias: ['shared', 'Выберите группу-источник и блок — его обновления будут общими'],
  };
  const escape = value => String(value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const normalize = value => String(value).toLocaleLowerCase('ru').replace(/ё/g, 'е').trim();
  const describe = item => {
    const fallback = /запис.*модул|модул.*запис/i.test(item.label) ? details.recording_modules : ['general', 'Добавить блок на страницу обучения'];
    const [category, description] = details[item.id] || fallback;
    return {...item, category, description};
  };
  let current = null;
  function open(items, onSelect, trigger = document.activeElement) {
    if (current) current.close();
    const choices = items.map(describe);
    const groups = categories.filter(([id]) => choices.some(item => item.category === id));
    let active = window.matchMedia('(max-width: 640px)').matches ? '' : (groups[0]?.[0] || ''), query = '';
    const dialog = document.createElement('dialog');
    current = dialog;
    dialog.className = 'course-block-picker';
    dialog.setAttribute('aria-labelledby', 'course-block-picker-title');
    const card = item => `<button type="button" class="cbp-choice" data-choice="${escape(item.id)}"><span class="cbp-choice-copy"><strong>${escape(item.label)}</strong><span>${escape(item.description)}</span></span><span class="cbp-plus" aria-hidden="true">+</span></button>`;
    dialog.innerHTML = `<div class="cbp-header"><div><h2 id="course-block-picker-title">Добавить блок</h2><p>Выберите категорию или найдите блок по названию</p></div><button type="button" class="cbp-close" aria-label="Закрыть выбор блока">×</button></div><div class="cbp-search"><label for="course-block-picker-search">Поиск по всем блокам</label><input id="course-block-picker-search" type="search" placeholder="Например, записи или сертификаты" autocomplete="off"></div><div class="cbp-layout"><nav class="cbp-nav" aria-label="Категории блоков">${groups.map(([id,label])=>`<button type="button" data-category="${id}">${label}<span>${choices.filter(item=>item.category===id).length}</span></button>`).join('')}</nav><div class="cbp-results">${groups.map(([id,label])=>`<section class="cbp-group" data-group="${id}"><h3><button type="button" data-category="${id}" aria-controls="cbp-items-${id}">${label}<span aria-hidden="true">⌄</span></button></h3><div class="cbp-items" id="cbp-items-${id}">${choices.filter(item=>item.category===id).map(card).join('')}</div></section>`).join('')}<p class="cbp-empty" role="status" hidden>Блоки не найдены — попробуйте другое название</p></div></div>${choices.filter(item=>item.category==='shared').map(item=>`<div class="cbp-shared">${card({...item,label:'Подключить общий блок из другого ЛК'})}</div>`).join('')}`;
    const search = dialog.querySelector('input');
    function update() {
      const matches = new Set(choices.filter(item => !query || normalize(item.label+' '+item.description+' '+(categories.find(([id])=>id===item.category)?.[1]||'')).includes(query)).map(item=>item.id));
      dialog.dataset.searching = query ? 'true' : 'false';
      dialog.querySelectorAll('[data-choice]').forEach(button => { button.hidden = !matches.has(button.dataset.choice); });
      dialog.querySelectorAll('[data-category]').forEach(button => {
        const selected = button.dataset.category === active;
        button.dataset.active = String(selected);
        if (button.closest('h3')) button.setAttribute('aria-expanded', String(!!query || selected));
        else button.setAttribute('aria-pressed', String(selected && !query));
      });
      dialog.querySelectorAll('[data-group]').forEach(section => {
        section.dataset.active = String(section.dataset.group === active);
        section.dataset.matches = String(choices.some(item=>item.category===section.dataset.group&&matches.has(item.id)));
      });
      const shared = dialog.querySelector('.cbp-shared');
      if (shared) shared.hidden = !matches.has('alias');
      dialog.querySelector('.cbp-empty').hidden = matches.size > 0;
    }
    dialog.addEventListener('click', event => {
      if (event.target === dialog || event.target.closest('.cbp-close')) { dialog.close(); return; }
      const category = event.target.closest('[data-category]');
      if (category) {
        const isMobileHeading = category.closest('h3');
        active = isMobileHeading && active === category.dataset.category && !query ? '' : category.dataset.category;
        query = ''; search.value = ''; update(); return;
      }
      const button = event.target.closest('[data-choice]');
      if (button) {
        const id = button.dataset.choice;
        if (!choices.some(item => item.id === id)) return;
        dialog.close(); onSelect(id);
      }
    });
    search.addEventListener('input', () => { query = normalize(search.value); update(); });
    dialog.addEventListener('close', () => {
      dialog.remove(); if (current === dialog) current = null;
      if (trigger?.isConnected) trigger.focus({preventScroll:true});
    }, {once:true});
    document.body.append(dialog); update(); dialog.showModal(); search.focus();
  }
  return {open};
})();
