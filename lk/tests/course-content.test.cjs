const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const content=require('../real-course-content.js');

test('real content imports once, backs up prior pages and preserves later edits and other groups',()=>{
  const groups={mba:{code:'ОНЛ_СПО-8',page_blocks:[{type:'info',data:{text:'Прежняя правка'}}]},recuGnPizfrDCbFKH:{code:'МБАстрат-7.1'},other:{page_blocks:[]}};
  const directory={staff:{selYPw5ErlQFBvICF:{name:'Вера Смирнова'}}};
  const state={groupOverrides:{},staffProfiles:{other:{text:'Мои контакты'}}};
  assert.equal(content.apply(state,groups,directory),true);
  assert.equal(state.previousRealCourseContent.mba.page_blocks[0].data.text,'Прежняя правка');
  assert.equal(groups.mba.page_blocks.find(b=>b.type==='homework').data.tasks.length,4);
  assert.equal(groups.mba.page_blocks.find(b=>b.type==='faq').data.questions.length,7);
  assert.equal(groups.recuGnPizfrDCbFKH.page_blocks.find(b=>b.type==='materials').data.resources.length,2);
  assert.equal(directory.staff.selYPw5ErlQFBvICF.name,'Вера Багрянцева');
  groups.mba.page_blocks[0].data.title='Правка после публикации';
  assert.equal(content.apply(state,groups,directory),false);
  assert.equal(groups.mba.page_blocks[0].data.title,'Правка после публикации');
  assert.deepEqual(groups.other.page_blocks,[]);
  assert.equal(state.staffProfiles.other.text,'Мои контакты');
});

function render(blocks,group={},groups={}){
  const main={innerHTML:'',classList:{add(){}},children:[]},nav={innerHTML:''};
  const sandbox={window:{},URL,URLSearchParams,Intl,Date,crypto:require('node:crypto').webcrypto,location:{search:''},SchoolPrograms:{coverFor(){return null;}},GroupHero:{render(){}},AdminTools:{avatar(){return '<span class="avatar"></span>';}},document:{querySelector(selector){return selector==='main.shell'?main:nav;}}};
  vm.createContext(sandbox);
  vm.runInContext(fs.readFileSync(require.resolve('../course-page.js'),'utf8'),sandbox);
  sandbox.window.CoursePages.render({programs:{},closes:()=>'',groups},{...group,page_blocks:blocks});
  return main.innerHTML;
}

test('artwork update replaces only the original intro photo and preserves later edits',()=>{
  const original='https://static.tildacdn.com/tild3362-6637-4938-b938-613537666336/joanna-kosinska-uGcD.jpg';
  for(const image of [original,'https://example.com/my-photo.jpg','']){
    const intro={type:'introductions',data:{block_id:'pilot-spo-intro',title:'Мой заголовок',url:'https://example.com/my-board',image_url:image}};
    const groups={mba:{page_blocks:[intro,{type:'info',data:{text:'Моя правка'}}]},other:{page_blocks:[]}};
    const state={realCourseContentVersion:content.version};
    assert.equal(content.apply(state,groups,{staff:{}}),true);
    assert.equal(intro.data.image_url,image===original?'':image);
    assert.equal(intro.data.title,'Мой заголовок');
    assert.equal(intro.data.url,'https://example.com/my-board');
    assert.equal(groups.mba.page_blocks[1].data.text,'Моя правка');
    assert.deepEqual(groups.other.page_blocks,[]);
    intro.data.image_url='https://example.com/later.jpg';
    assert.equal(content.apply(state,groups,{staff:{}}),false);
    assert.equal(intro.data.image_url,'https://example.com/later.jpg');
  }
});

test('new blocks escape text and reject unsafe links; pending video never exposes its draft URL',()=>{
  const html=render([
    {type:'homework',data:{tasks:[{title:'<script>alert(1)</script>',status:'past',deadline:'2026-11-10',url:'javascript:alert(1)'}]}},
    {type:'faq',data:{questions:[{question:'<img src=x>',answer:'Первый абзац\nВторой абзац',url:'https://example.com/faq'}]}},
    {type:'recordings',data:{videos:[{title:'Будущая запись',status:'pending',url:'https://example.com/draft'},{title:'Старая запись',url:'https://example.com/published'}]}},
    {type:'materials',data:{url:'https://example.com/legacy',resources:[{title:'Книги',url:'https://example.com/books'}]}},
    {type:'video',data:{image_url:'javascript:alert(1)',text:'Видео'}}
  ]);
  assert.ok(html.includes('&lt;script&gt;'));
  assert.ok(html.includes('&lt;img src=x&gt;'));
  assert.ok(!html.includes('javascript:'));
  assert.ok(!html.includes('https://example.com/draft'));
  for(const value of ['Видео ещё не опубликовано','Прошедшее','https://example.com/published','https://example.com/legacy','https://example.com/books','Первый абзац\nВторой абзац'])assert.ok(html.includes(value),value);
});

test('all imported resource and contact links use HTTP or HTTPS',()=>{
  function visit(value){
    if(!value||typeof value!=='object')return;
    for(const [key,item] of Object.entries(value)){
      if((key==='url'||key.endsWith('_url'))&&item)assert.ok(['http:','https:'].includes(new URL(item).protocol),key);
      else if(item&&typeof item==='object')visit(item);
    }
  }
  visit(content.groups);visit(content.staffProfiles);
});

test('past homework is collapsed by status while current tasks and custom actions stay available',()=>{
  const past={title:'Архивное задание',status:'past',url:'https://example.com/past',button:'Открыть'};
  const current={title:'Текущее задание',status:'upcoming',deadline:'2000-01-01',url:'https://example.com/current',button:'Моя подпись'};
  for(const tasks of [[past,current],[past],[current],[]]){
    const html=render([{type:'homework',data:{tasks}}]);
    const archive=html.match(/<details class="past-homework">([\s\S]*?)<\/details>/)?.[1]||'';
    assert.equal(Boolean(archive),tasks.includes(past));
    assert.ok(!/<details class="past-homework"[^>]*\bopen\b/.test(html));
    if(tasks.includes(past)){
      assert.ok(archive.includes('Архивное задание'));
      assert.ok(archive.includes('https://example.com/past'));
      assert.ok(archive.includes('Перейти к заданию'));
    }
    if(tasks.includes(current)){
      assert.ok(html.includes('Текущее задание'));
      assert.ok(!archive.includes('Текущее задание'));
      assert.ok(html.includes('Моя подпись'));
    }
  }
});

test('legacy contacts become safe actionable links, omit repeated name and keep multiple chats',()=>{
  const html=render([{type:'contacts',data:{chats:[{label:'MAX',url:'https://max.ru/group'},{label:'Telegram',url:'https://t.me/group'},{label:'Unsafe',url:'javascript:alert(1)'}]}}],{owner:'Дарья Маврина',staffProfile:{text:'  Дарья   Маврина\nemail: dasha+group@example.com\nтел.: 8-000-000-00-00\nГрафик работы: пн-пт с 10:00 до 18:00\n<script>alert(1)</script>',contact_url:'https://max.ru/admin'}}).split('<section id="help"')[0];
  assert.equal((html.match(/Дарья Маврина/g)||[]).length,1);
  for(const value of ['href="mailto:dasha%2Bgroup@example.com"','href="tel:+70000000000"','10:00 до 18:00','https://max.ru/group','https://t.me/group','Написать администратору','&lt;script&gt;'])assert.ok(html.includes(value),value);
  assert.ok(!html.includes('javascript:'));
  assert.ok(!html.includes('<script>'));
  assert.ok(!html.includes('tel:10'));
});

test('recordings show the date once and preserve meaningful custom copy',()=>{
  const html=render([{type:'recordings',data:{videos:[
    {date:'2026-10-19',title:'1 занятие 19.10.2026',text:'Запись занятия от 19.10.2026.',url:'https://example.com/one'},
    {date:'2026-10-26',title:'Разбор кейса — 26.10.2026',text:'Разбор решений команды',url:'https://example.com/two'},
    {title:'Без даты',text:'Полезное описание',status:'pending'}
  ]}}]);
  assert.equal((html.match(/19\.10\.2026/g)||[]).length,1);
  assert.equal((html.match(/26\.10\.2026/g)||[]).length,1);
  for(const value of ['Занятие 1','<h3>Разбор кейса</h3>','Разбор решений команды','Без даты','Полезное описание','https://example.com/one','https://example.com/two'])assert.ok(html.includes(value),value);
});

test('recordings follow chronology with archive first, latest four open and planned last',()=>{
  const videos=Array.from({length:6},(_,i)=>({date:`2026-01-0${i+1}`,title:`Topic ${i+1}`,url:`https://example.com/video-${i+1}`})).reverse();
  videos.unshift({date:'2099-01-02',title:'Future 2',status:'pending'},{date:'2099-01-01',title:'Future 1',status:'pending'});
  const html=render([{type:'recordings',data:{videos}}]);
  const archive=html.split('</details>')[0],current=html.split('</details>')[1].split('<details')[0];
  assert.equal((archive.match(/class="recording-row/g)||[]).length,2);
  assert.equal((current.match(/class="recording-row/g)||[]).length,4);
  const labels=['Topic 1','Topic 2','Topic 3','Topic 4','Topic 5','Topic 6','Future 1','Future 2'];
  const positions=labels.map(label=>html.indexOf(label));
  assert.ok(positions.every(p=>p>=0));
  assert.deepEqual(positions,[...positions].sort((a,b)=>a-b));
  assert.ok(!archive.includes(' open'));
  for(let i=1;i<=6;i++)assert.ok(html.includes(`https://example.com/video-${i}`));
});

test('materials cleanup is targeted and one-time, keeping custom descriptions and multiple resources',()=>{
  for(const description of ['Дополнительные материалы группы ОНЛ_СПО-8.','Сначала изучите инструкцию']){
    const block={type:'materials',data:{block_id:'pilot-spo-materials',title:'Материалы для обучения',text:description,resources:[{title:'Материалы группы',url:'https://example.com/folder'}]}};
    const groups={mba:{page_blocks:[block]}};
    const state={realCourseContentVersion:content.version,welcomeArtworkRefined:true};
    content.apply(state,groups,{staff:{}});
    assert.equal(block.data.text,description.startsWith('Дополнительные')?'':description);
    const html=render([block]);assert.ok(!html.includes('<h3>Материалы группы</h3>'));assert.ok(html.includes('https://example.com/folder'));
    block.data.text='Моя новая подпись';assert.equal(content.apply(state,groups,{staff:{}}),false);assert.equal(block.data.text,'Моя новая подпись');
  }
  const html=render([{type:'materials',data:{resources:[{title:'Папка группы',url:'https://example.com/group'},{title:'Библиотека MBA',url:'https://example.com/books'}]}},{type:'faq',data:{questions:[{question:'Мой вопрос',answer:'Мой ответ'}]}}]);
  for(const value of ['Папка группы','Библиотека MBA','https://example.com/group','https://example.com/books','Мой вопрос'])assert.ok(html.includes(value));
  assert.ok(!html.includes('id="help"'));
});

test('schedule highlights are manual, optional comments are escaped and visible without highlighting',()=>{
 const lessons=[{date:'2026-12-10',time:'18:00–21:00',title:'Особое занятие',highlight:true,highlight_type:'day',comment:'<script>не выполнять</script>'},{date:'2026-12-11',time:'18:00–21:00',title:'Другой день без пометки',comment:'Комментарий без выделения'}];
 const html=render([{type:'schedule',data:{title:'Расписание',visible:true,lessons}}]);
 assert.equal((html.match(/class="lesson-highlighted"/g)||[]).length,1);
 assert.ok(html.includes('Другой день</span>'));
 assert.ok(html.includes('&lt;script&gt;не выполнять&lt;/script&gt;'));
 assert.ok(!html.includes('<script>не выполнять'));
 assert.ok(html.includes('Комментарий без выделения'));
 lessons[0].highlight=false;
 assert.ok(!render([{type:'schedule',data:{visible:true,lessons}}]).includes('lesson-highlighted'));
});


test('material icons keep links and custom buttons, with safe folder fallback',()=>{
  const html=render([{type:'materials',data:{url:'https://example.com/main',resources:[
    {title:'Книги',icon:'book',url:'https://example.com/books',button:'Читать'},
    {title:'Фильмы',icon:'film',url:'https://example.com/films',button:'Смотреть'},
    {title:'Старый материал',url:'https://example.com/legacy'},
    {title:'Неизвестный значок',icon:'<script>bad</script>'}
  ]}}]);
  for(const value of ['data-material-icon="book"','data-material-icon="film"','https://example.com/books','https://example.com/films','Читать','Смотреть'])assert.ok(html.includes(value),value);
  assert.equal((html.match(/data-material-icon="folder"/g)||[]).length,3);
  assert.ok(!html.includes('<script>bad</script>'));
});


test('schedule notices stay between lessons across years and escape arbitrary text',()=>{
  const html=render([{type:'schedule',data:{lessons:[{date:'2027-01-12',title:'После каникул'},{date:'2026-12-22',title:'До каникул'}],announcements:[
    {start_date:'2026-12-23',end_date:'2027-01-11',text:'Каникулы <script>bad</script>'},
    {start_date:'2026-12-24',end_date:'2026-12-01',text:'Неверный период'},
    {start_date:'2026-02-31',end_date:'2026-03-03',text:'Несуществующая дата'}
  ]}}]);
  assert.ok(html.indexOf('До каникул')<html.indexOf('Каникулы &lt;script&gt;'));
  assert.ok(html.indexOf('Каникулы &lt;script&gt;')<html.indexOf('После каникул'));
  for(const text of ['23.12.2026','11.01.2027'])assert.ok(html.includes(text));
  for(const text of ['<script>bad</script>','Неверный период','Несуществующая дата'])assert.ok(!html.includes(text));
  assert.equal((html.match(/class="schedule-announcement"/g)||[]).length,1);
});

test('an announcement can appear without any lessons',()=>{
  const html=render([{type:'schedule',data:{announcements:[{start_date:'2026-12-23',end_date:'2026-12-23',text:'Учебная встреча'}]}}]);
  assert.ok(html.includes('Учебная встреча'));
  assert.ok(!html.includes('Расписание ещё не опубликовано'));
});

test('coursework uses independent disclosures, configurable text, both modes and no removed actions',()=>{
  const data={intro:'Для группы нужны 7 консультаций.',mode:'individual',consultation_points:[{icon:'info',title:'<script>test</script>',text:'Условия'}],defense_steps:[{title:'Тайминг',text:'20 минут'}]};
  const html=render([{type:'coursework',data}]);
  assert.ok(html.includes('Индивидуальные консультации по записи'));
  assert.ok(html.includes('7 консультаций'));
  assert.ok(html.includes('&lt;script&gt;test&lt;/script&gt;'));
  assert.equal((html.match(/<details class="coursework-disclosure"/g)||[]).length,2);
  assert.equal((html.match(/<details class="coursework-disclosure" open>/g)||[]).length,1);
  for(const unwanted of ['Написать администратору','>Расписание консультаций','Свободное посещение','<script>test</script>'])assert.ok(!html.includes(unwanted),unwanted);
  const free=render([{type:'coursework',data:{...data,mode:'free',defense_steps:[]}}]);
  assert.ok(free.includes('Свободное посещение'));
  assert.ok(!free.includes('<summary>Защита курсовой работы</summary>'));
});

test('consultation schedule stays separate and reflects edits from its shared source',()=>{
  const source={page_blocks:[{type:'consultations',data:{block_id:'shared',sessions:[{date:'2027-01-15',time:'17:00–20:00',title:'Поздняя тема',comment:'По записи',url:'javascript:bad()'},{date:'2027-01-10',time:'12:00–13:00',title:'Ранняя тема'}]}}]};
  const blocks=[{type:'alias',data:{title:'Общее расписание',source_group:'source',source_block:'shared'}},{type:'coursework',data:{mode:'free'}}];
  let html=render(blocks,{}, {source});
  assert.ok(html.indexOf('Ранняя тема')<html.indexOf('Поздняя тема'));
  assert.ok(html.includes('Тема консультации'));
  assert.ok(html.includes('По записи'));
  assert.ok(!html.includes('javascript:'));
  source.page_blocks[0].data.sessions[0].title='Обновлённая тема';
  html=render(blocks,{}, {source});
  assert.ok(html.includes('Обновлённая тема'));
  assert.ok(html.includes('Свободное посещение'));
  assert.ok(!html.includes('Поздняя тема'));
});
