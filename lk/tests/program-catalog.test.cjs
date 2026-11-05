const {test}=require('node:test');
const assert=require('node:assert/strict');
const catalog=require('../school-programs.js');

test('imports approved catalog and preserves existing IDs, manual templates and unrelated records',()=>{
  const source=catalog.records.find(p=>p.tag==='ОНЛ_СПО');
  const state={programs:{spo:{title:'Название группы',tag:'ОНЛ_СПО',template_key:'custom',page_blocks:[{type:'info',data:{text:'Мой текст'}}]},commercial:{title:'MBA Коммерческий директор',tag:'ДЕМО_КД'},personal:{title:'Моя программа',tag:'Моя'}}};
  assert.deepEqual(catalog.apply(state),{added:35,updated:2});
  assert.equal(state.programs.spo.source_id,source.source_id);
  assert.equal(state.programs.spo.template_key,'custom');
  assert.equal(state.programs.spo.page_blocks[0].data.text,'Мой текст');
  assert.equal(state.programs.commercial.tag,'МБАКД');
  assert.equal(state.programs.personal.title,'Моя программа');
  assert.equal(state.previousProgramCatalog.spo.title,'Название группы');
  state.programs.spo.title='Последующая ручная правка';
  assert.deepEqual(catalog.apply(state),{added:0,updated:0});
  assert.equal(state.programs.spo.title,'Последующая ручная правка');
  assert.equal(Object.values(state.programs).filter(p=>p.source_id).length,37);
});

test('approved selection excludes five unresolved records and supplies four site covers',()=>{
  assert.equal(catalog.records.length,37);
  assert.equal(new Set(catalog.records.map(p=>p.tag)).size,37);
  assert.equal(catalog.records.filter(p=>p.program_type==='Программа проф. переподготовки').length,4);
  assert.equal(catalog.records.filter(p=>p.program_type==='Программа повышения квалификации').length,14);
  assert.equal(catalog.records.filter(p=>p.formats.includes('онлайн')).length,23);
  assert.equal(catalog.records.filter(p=>p.cover_url).length,4);
  for(const p of catalog.records){assert.ok(p.tag&&p.program_type&&p.hours);if(p.cover_url)assert.ok(catalog.safeCover(p.cover_url));}
  assert.ok(!catalog.records.some(p=>['ЧРдир','ФДСтарт','ШколаФасилитации','ОНЛ_ПроизвПродукт'].includes(p.tag)||p.title==='MBA General'));
  assert.equal(catalog.safeCover('https://veshk.ru.attacker.invalid/media/images/x.jpg'),'');
  assert.equal(catalog.safeCover('javascript:alert(1)'),'');
  assert.equal(catalog.safeCover('https://user@veshk.ru/media/images/x.jpg'),'');
});
