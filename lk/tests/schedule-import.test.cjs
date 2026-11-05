const {test}=require('node:test');
const assert=require('node:assert/strict');
const {parse,merge,sourceUrl}=require('../schedule-import.js');
const source='Расписание программы\nУчебный пример\nМесяц\tДата\tДень\tВремя\tТема\tПреподаватель\tЧасы\tID занятия\nНоябрь\t06.11.2026\tпятница\t10.00-19.00\tФинансы\tПримеров\t10\tone\n\t11.11.2026\tсреда\t17.00-21.00 Бюджетирование\t\tПримеров\t5\ttwo';
test('append preserves existing rows, ignores matching IDs and content, and is idempotent',()=>{
  const current=[{source_id:'one',date:'2026-11-05',time:'10:00–19:00',title:'Финансы вручную',teacher:'Примеров',url:'https://example.com/join'},{date:'2026-11-11',time:'17:00–21:00',title:'Бюджетирование',teacher:'Примеров'}];
  const input=parse(source+'\n\t20.11.2026\t\t10:00–12:00\tНовая тема\tЛектор\t2\tthree');
  const added=merge(input,current,'append');assert.deepEqual(added.counts,{added:1,changed:0,same:2,removed:0});assert.equal(added.lessons.length,3);assert.deepEqual(added.lessons[0],current[0]);assert.deepEqual(merge(input,added.lessons,'append').counts,{added:0,changed:0,same:3,removed:0});
});
test('school schedule snapshot has valid dated lessons, exact group mapping and retained undated MBA rows',()=>{
  const context={window:{}};require('node:vm').runInNewContext(require('node:fs').readFileSync(require('node:path').join(__dirname,'../school-schedules.js'),'utf8'),context);const data=context.window.SchoolSchedules;
  assert.equal(Object.keys(data.groups).length,12);assert.equal(Object.values(data.groups).reduce((n,g)=>n+g.lessons.length,0),213);
  assert.equal(data.groups.recS2rmHHPsThSzgy.lessons.length,0);assert.equal(data.groups.recuGnPizfrDCbFKH.pending_lessons.length,7);
  for(const group of Object.values(data.groups)){
    if(!group.lessons.length)continue;
    const fields=['date','time','title','teacher','hours','source_id'];const csv=['Дата\tВремя\tТема\tПреподаватель\tЧасы\tID занятия',...group.lessons.map(l=>fields.map(k=>'"'+String(l[k]||'').replace(/"/g,'""')+'"').join('\t'))].join('\n');
    assert.deepEqual(parse(csv).errors,[],group.code);
    if(group.code.startsWith('Росско/'))assert.ok(group.lessons.every(l=>l.title.startsWith(group.code.slice(-1)+' группа')));
  }
});
test('school layout detects heading and separated topic in time cell',()=>{
  const p=parse(source);assert.deepEqual(p.errors,[]);assert.equal(p.header,3);assert.equal(p.lessons.length,2);assert.equal(p.lessons[0].time,'10:00–19:00');assert.equal(p.lessons[0].hours,'10');assert.equal(p.lessons[1].title,'Бюджетирование');assert.equal(p.warnings.length,1);
});
test('repeat update does not duplicate, reschedule preserves local extras by ID',()=>{
  const p=parse(source),old=[{source_id:'one',date:'2026-11-05',module:'Модуль',url:'https://example.com'}, {source_id:'old',date:'2026-11-02',title:'Removed'}];
  const m=merge(p,old);assert.deepEqual(m.counts,{added:1,changed:1,same:0,removed:1});assert.equal(m.lessons[0].module,'Модуль');assert.equal(m.lessons[0].url,'https://example.com');assert.deepEqual(merge(p,m.lessons).counts,{added:0,changed:0,same:2,removed:0});
});
test('CSV quotes and exact tag filtering',()=>{
  const p=parse('ТЕГ группы,Дата,Время,Занятие\nКД-1,06.11.2026,10:00–19:00,"Финансы, бюджет"\nКД-10,06.11.2026,10:00–19:00,Other','КД-1');assert.deepEqual(p.errors,[]);assert.equal(p.lessons[0].title,'Финансы, бюджет');assert.equal(p.skipped,1);assert.equal(p.warnings.length,1);
});
test('invalid rows, duplicate IDs, empty import and unsafe URLs block import',()=>{
  const p=parse('Дата;Время;Тема;ID занятия;Ссылка\n31.02.2026;25:00-17:00;Bad;one;javascript:alert(1)\n06.11.2026;19:00-10:00;Bad;one;');assert.ok(p.errors.length>=5);assert.ok(parse('Дата\tВремя\tТема').errors.length);assert.ok(parse(source+'\n\t06.11.2026\t\t10:00–19:00\tФинансы\tПримеров\t10\tx').errors.length);
});
test('HTML login and malformed CSV rejected',()=>{
  assert.throws(()=>parse('<!doctype html><title>Login</title>'),/входа/);assert.throws(()=>parse('Дата,Время,Тема\n06.11.2026,10:00–19:00,"unfinished'),/кавычки/);assert.throws(()=>parse('x'.repeat(2000001)),/2 МБ/);
});
test('explicit optional empty field clears while absent field stays',()=>{
  const p=parse('Дата\tВремя\tТема\tID занятия\tМодуль\n06.11.2026\t10:00–19:00\tФинансы\tone\t');const m=merge(p,[{source_id:'one',module:'Old',url:'https://example.com'}]);assert.equal(m.lessons[0].module,'');assert.equal(m.lessons[0].url,'https://example.com');
});
test('gid is retained and source URL is limited to Google Sheets',()=>{
  assert.equal(sourceUrl('https://docs.google.com/spreadsheets/d/example/edit#gid=42'),'https://docs.google.com/spreadsheets/d/example/export?format=csv&gid=42');assert.equal(sourceUrl('https://docs.google.com/spreadsheets/d/e/example/pubhtml?gid=7'),'https://docs.google.com/spreadsheets/d/e/example/pub?output=csv&gid=7');assert.throws(()=>sourceUrl('https://docs.google.com.attacker.invalid/spreadsheets/d/example/edit'));assert.throws(()=>sourceUrl('https://user:secret@docs.google.com/spreadsheets/d/example/edit'));
});
