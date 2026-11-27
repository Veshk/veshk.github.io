import {materialCards,sampleMaterials} from './material-cards.mjs?v=20261127-materials-1';
import {enhanceLessonMaterials} from './lesson-materials.mjs?v=20261127-materials-1';
const example=document.querySelector('#example');
function render(mode){
 const project=mode==='project';
 example.innerHTML=`<h2>${project?'Основные роли в проекте':'Практическое задание'}</h2>`+(project?materialCards('Шаблон проекта\nОпишите цель, ожидаемый результат и роли участников вашего проекта. Используйте вопросы в шаблоне.\n\nПримеры проектов\nПосмотрите, как можно описать роли в двух разных проектах. После этого дополните собственный вариант.',[{name:'Шаблон проекта',type:'PDF',url:'sample-material.pdf'},{name:'Примеры проектов',type:'PDF',url:'sample-material.pdf'}]):sampleMaterials());
 example.querySelectorAll('[data-lesson-materials]').forEach(enhanceLessonMaterials);
 document.querySelectorAll('[data-example]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.example===mode)));
}
document.querySelectorAll('[data-example]').forEach(b=>b.addEventListener('click',()=>render(b.dataset.example)));
render('assignment');
