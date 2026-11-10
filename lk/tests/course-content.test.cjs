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

function render(blocks,group={}){
  const main={innerHTML:''},nav={innerHTML:''};
  const sandbox={window:{},URL,URLSearchParams,Intl,Date,crypto:require('node:crypto').webcrypto,location:{search:''},GroupHero:{render(){}},AdminTools:{avatar(){return '<span class="avatar"></span>';}},document:{querySelector(selector){return selector==='main.shell'?main:nav;}}};
  vm.createContext(sandbox);
  vm.runInContext(fs.readFileSync(require.resolve('../course-page.js'),'utf8'),sandbox);
  sandbox.window.CoursePages.render({programs:{},closes:()=>'',groups:{}},{...group,page_blocks:blocks});
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
  const html=render([{type:'contacts',data:{chats:[{label:'MAX',url:'https://max.ru/group'},{label:'Telegram',url:'https://t.me/group'},{label:'Unsafe',url:'javascript:alert(1)'}]}}],{owner:'Дарья Маврина',staffProfile:{text:'  Дарья   Маврина\nemail: dasha+group@example.com\nтел.: 8-999-286-67-07\nГрафик работы: пн-пт с 10:00 до 18:00\n<script>alert(1)</script>',contact_url:'https://max.ru/admin'}}).split('<section id="help"')[0];
  assert.equal((html.match(/Дарья Маврина/g)||[]).length,1);
  for(const value of ['href="mailto:dasha%2Bgroup@example.com"','href="tel:+79992866707"','10:00 до 18:00','https://max.ru/group','https://t.me/group','Написать администратору','&lt;script&gt;'])assert.ok(html.includes(value),value);
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
