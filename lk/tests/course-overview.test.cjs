const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const sandbox={window:{},Intl,Date,URL};
vm.createContext(sandbox);
vm.runInContext(fs.readFileSync(require.resolve('../course-overview.js'),'utf8'),sandbox);
const overview=sandbox.window.CourseOverview;
const schedule=lessons=>({type:'schedule',data:{visible:true,show_moscow:true,lessons}});
const rows=[{date:'2026-11-11',time:'10:00–13:00',title:'Утро'},{date:'2026-11-11',time:'14:00–17:15',title:'День'},{date:'2026-11-12',time:'10:00–13:00',title:'Завтра'}];
test('nearest day includes every session until the last session ends in EKB',()=>{
 const blocks=[schedule([...rows].reverse())];
 for(const now of ['2026-11-10T20:00:00Z','2026-11-11T08:30:00Z','2026-11-11T12:14:59Z'])assert.equal(overview.upcoming(blocks,new Date(now)).map(x=>x.title).join(','),'Утро,День');
 assert.equal(overview.upcoming(blocks,new Date('2026-11-11T12:15:00Z'))[0].title,'Завтра');
 assert.equal(overview.upcoming(blocks,new Date('2026-11-13T00:00:00Z')).length,0);
});
test('invalid dates and hidden schedules do not displace visible lessons; unknown times remain today',()=>{
 const hidden=schedule([{date:'2026-11-10',title:'Скрыто'}]);hidden.data.visible=false;
 const blocks=[hidden,schedule([{date:'2026-02-30',title:'Ошибка'}, {date:'2026-11-11',title:'Без времени'},...rows])];
 assert.equal(overview.upcoming(blocks,new Date('2026-11-11T18:00:00Z')).map(x=>x.title).join(','),'Утро,День,Без времени');
});
test('overview escapes content and rejects unsafe email, photo and links',()=>{
 const html=overview.markup([schedule([{...rows[0],title:'<script>x</script>',url:'javascript:alert(1)'}])],{academic_advisor_id:'a'},{a:{name:'<img src=x>',email:'x@example.com?bcc=y',photo:'data:image/svg+xml;base64,aaa'}},new Date('2026-11-11T01:00:00Z'));
 assert.ok(html.includes('&lt;script&gt;'));assert.ok(html.includes('&lt;img'));assert.ok(!html.includes('javascript:'));assert.ok(!html.includes('mailto:'));assert.ok(!html.includes('<img src='));
 const good=overview.markup([],{academic_advisor_id:'a'},{a:{name:'Имя',email:'test+group@example.com',photo:'https://example.com/photo.png'}});
 assert.ok(good.includes('data-advisor-question-open'));assert.ok(!good.includes('mailto:'));assert.ok(good.includes('https://example.com/photo.png'));assert.ok(good.includes('Новые занятия пока не запланированы'));
});
test('KD import is one-time and preserves prior groups and adviser edits',()=>{
 const content=require('../real-course-content.js');
 const state={realCourseContentVersion:content.version,welcomeArtworkRefined:true,materialsCopyRefined:true,academicAdvisors:{rublevskaya:{name:'Своя правка',email:'custom@example.com'}}};
 const groups={mba:{description:'Правка СПО'},kd26:{name:'КД-26'}};
 content.apply(state,groups,{staff:{}});
 const lessons=groups.kd26.page_blocks.find(x=>x.type==='schedule').data.lessons;
 assert.equal(lessons.length,32);assert.equal(lessons.filter(x=>x.date==='2026-04-04').length,2);
 assert.equal(lessons.filter(x=>x.date==='2026-07-29').length,1);
 assert.equal(groups.kd26.page_blocks.find(x=>x.type==='recordings').data.videos.length,3);
 assert.equal(groups.kd26.page_blocks.find(x=>x.type==='faq').data.questions.length,5);
 assert.equal(groups.mba.description,'Правка СПО');assert.equal(state.academicAdvisors.rublevskaya.email,'custom@example.com');
 groups.kd26.description='Своя правка КД';groups.kd26.academic_advisor_id=null;
 assert.equal(content.apply(state,groups,{staff:{}}),false);assert.equal(groups.kd26.description,'Своя правка КД');assert.equal(groups.kd26.academic_advisor_id,null);
});

test('nearest lesson shows the same manual notice and safely renders its comment',()=>{
 const lessons=[{...rows[0],highlight:true,highlight_type:'time',comment:'<img src=x onerror=alert(1)>'},rows[1]];
 const html=overview.markup([schedule(lessons)],{},{},new Date('2026-11-11T01:00:00Z'));
 assert.equal((html.match(/lesson-highlighted/g)||[]).length,1);
 assert.ok(html.includes('Другое время'));
 assert.ok(html.includes('&lt;img src=x onerror=alert(1)&gt;'));
 assert.ok(!html.includes('<img src=x'));
 lessons[0].highlight=false;
 assert.ok(!overview.markup([schedule(lessons)],{},{},new Date('2026-11-11T01:00:00Z')).includes('lesson-highlighted'));
});


test('program adviser is inherited, while group override and hiding remain independent',()=>{
 const programs={p:{academic_advisor_id:'shared'}},people={shared:{name:'Общий'},custom:{name:'Индивидуальный'},next:{name:'Новый'}};
 const group={program_id:'p'},now=new Date('2026-11-23T12:00:00Z');
 assert.ok(overview.markup([],group,people,now,programs).includes('Общий'));
 group.academic_advisor_id='custom';programs.p.academic_advisor_id='next';
 assert.ok(overview.markup([],group,people,now,programs).includes('Индивидуальный'));
 group.show_academic_advisor=false;
 assert.ok(!overview.markup([],group,people,now,programs).includes('advisor-card'));
 group.show_academic_advisor=true;group.academic_advisor_id=null;
 assert.ok(overview.markup([],group,people,now,programs).includes('Новый'));
 programs.p.academic_advisor_id=null;
 assert.ok(!overview.markup([],group,people,now,programs).includes('advisor-card'));
});
test('advisor directory includes inherited and hidden assignments but excludes replaced advisor',()=>{
 vm.runInContext(fs.readFileSync(require.resolve('../academic-advisors.js'),'utf8'),sandbox);
 sandbox.CourseOverview=overview;
 const groups={inherited:{program_id:'p'},hidden:{program_id:'p',show_academic_advisor:false},custom:{academic_advisor_id:'a'},other:{program_id:'p',academic_advisor_id:'b'}};
 const result=sandbox.window.AcademicAdvisors.assignments('a',groups,{p:{academic_advisor_id:'a'}});
 assert.equal(result.programs.map(x=>x[0]).join(','),'p');
 assert.equal(result.groups.map(x=>x[0]).join(','),'inherited,hidden,custom');
});
