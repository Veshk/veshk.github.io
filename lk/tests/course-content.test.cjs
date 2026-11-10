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

function render(blocks){
  const main={innerHTML:''},nav={innerHTML:''};
  const sandbox={window:{},URL,URLSearchParams,Intl,Date,crypto:require('node:crypto').webcrypto,location:{search:''},GroupHero:{render(){}},document:{querySelector(selector){return selector==='main.shell'?main:nav;}}};
  vm.createContext(sandbox);
  vm.runInContext(fs.readFileSync(require.resolve('../course-page.js'),'utf8'),sandbox);
  sandbox.window.CoursePages.render({programs:{},closes:()=>'',groups:{}},{page_blocks:blocks});
  return main.innerHTML;
}

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
