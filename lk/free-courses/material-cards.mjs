import {escapeHtml as h} from './course-view.mjs?v=20261127-materials-1';
export function materialCards(description, files) {
  return `<div data-lesson-materials><div class="lm-plain-description" data-lesson-description>${h(description)}</div><div class="lm-file-list">${files.map(f=>`<article class="lm-file" data-lesson-file><div class="lm-file-heading"><span class="lm-file-type" aria-hidden="true">${h(f.type||'ФАЙЛ')}</span><h4 class="lm-file-title" data-file-title>${h(f.name)}</h4></div><p data-file-description>${h(f.description||'')}</p><div class="lm-file-actions">${f.type==='PDF'?`<a class="lm-button" href="${h(f.url)}" target="_blank" rel="noopener" aria-label="Открыть PDF: ${h(f.name)} (новая вкладка)">Открыть PDF ↗</a>`:''}<a class="lm-button ${f.type==='PDF'?'lm-button-secondary':''}" href="${h(f.url)}" ${f.demoResource?'target="_blank" rel="noopener"':'download'} aria-label="Скачать: ${h(f.name)}">${f.demoResource?'Открыть пример':'Скачать ↓'}</a>${f.type==='PDF'?'<span class="lm-file-note">PDF откроется в новой вкладке</span>':''}</div></article>`).join('')}</div></div>`;
}
export function sampleMaterials() {
 return materialCards('Выберите одну рабочую задачу. Опишите ожидаемый результат и три шага, которые помогут его достичь.\n\n1. Откройте файл задания.\n2. Запишите свой вариант решения.\n3. Раскройте ответ и проверьте себя.', [
 {name:'Задание',type:'PDF',url:'sample-material.pdf',description:'Краткая инструкция и вопросы для самостоятельной работы.'},
 {name:'Ответ',type:'PDF',url:'sample-material.pdf',description:'Демонстрационный файл для проверки открытия PDF. Здесь в уроке будет разбор задания.'}
 ]);
}
