const test = require('node:test');
const assert = require('node:assert/strict');
let organizeLessonMaterials, cleanLessonText;
test.before(async()=>({organizeLessonMaterials,cleanLessonText}=await import('../free-courses/lesson-materials.mjs')));

test('legacy competence tabs become matching cards and a bibliography without whitespace gaps', () => {
  const content = 'Задание\nВыявите ошибки в компетенции.\n*Файл доступен для скачивания\n\nОшибки\nКомментарии преподавателя.\n\nОтвет\nИсправленный вариант.\nСписок литературы по теме компетенции\n'+ ' \n'.repeat(18)+'Книга 1\nКнига 2';
  const result = organizeLessonMaterials(content, ['Задание','Ошибки','Ответ'].map((title,index)=>({title,index})));
  assert.equal(result.intro, '');
  assert.deepEqual(result.cards.map(c=>[c.index,c.description]), [[0,'Выявите ошибки в компетенции.'],[1,'Комментарии преподавателя.'],[2,'Исправленный вариант.']]);
  assert.equal(result.extras[0].text, 'Книга 1\nКнига 2');
});
test('project charter descriptions remain beside their original indexed files', () => {
  const result = organizeLessonMaterials('Шаблон Устава\nНапишите Устав вашего проекта.\n\nПримеры Уставов\nВнешний и внутренний проект.', [{title:'Шаблон Устава',index:4},{title:'Примеры Уставов',index:8}]);
  assert.equal(result.cards[0].index,4); assert.equal(result.cards[1].index,8);
  assert.equal(result.cards[1].description,'Внешний и внутренний проект.');
});
test('course-wide task and files without matching headings preserve every instruction', () => {
  const result = organizeLessonMaterials('Практическое задание к курсу\n\n1. Создайте запрос.\n2. Обновите данные.', [{title:'январь.xlsx',index:0},{title:'март.xlsx',index:2}]);
  assert.match(result.intro,/1\. Создайте запрос/); assert.equal(result.cards.length,2);
});
test('ambiguous headings do not attach text to the wrong file', () => {
  const result = organizeLessonMaterials('Задание\nОбщее описание', [{title:'Задание',index:0},{title:'Задание',index:1}]);
  assert.equal(result.intro,'Задание\nОбщее описание'); assert.ok(result.cards.every(c=>c.description===''));
});
test('new per-file descriptions coexist with legacy text without loss or duplication', () => {
  let result=organizeLessonMaterials('Задание\nСтарое описание',[{title:'Задание',description:'Новое пояснение'}]);
  assert.equal(result.cards[0].description,'Старое описание\n\nНовое пояснение');
  result=organizeLessonMaterials('Задание\nОдно описание',[{title:'Задание',description:'Одно описание'}]);
  assert.equal(result.cards[0].description,'Одно описание');
});
test('text-only recommendations and URL instructions are preserved', () => {
  const result=organizeLessonMaterials('Прочитайте книгу.\n\nПолезные ссылки\nhttps://example.test/page',[]);
  assert.equal(result.intro,'Прочитайте книгу.');assert.equal(result.extras[0].text,'https://example.test/page');
  assert.equal(cleanLessonText(' Первая\r\n \n\u00a0\n\nВторая '),'Первая\n\nВторая');
});
