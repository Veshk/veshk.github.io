const {test}=require('node:test');const assert=require('node:assert/strict');
const modulePath='../free-courses/feedback-summary.mjs';
test('summary groups current answers and filters local calendar boundaries, course and search',async()=>{
 const {selectFeedback,summarizeFeedback}=await import(modulePath);const {courses}=await import('../free-courses/domain.mjs');const c=courses[0],d=courses[1];
 const state={people:[{id:'u',name:'Иван',email:'i@example.test'}],enrollments:[{id:'1',userId:'u',courseId:c.id,feedback:{rating:5,submittedAt:'2026-11-26T19:00:00Z'}},{id:'2',userId:'u',courseId:d.id,feedback:{rating:3,submittedAt:'2026-11-27T18:59:59Z'}},{id:'3',userId:'u',courseId:c.id,feedback:{rating:4,submittedAt:'2026-11-27T19:00:00Z'}},{id:'4',userId:'u',courseId:c.id}]};
 assert.equal(selectFeedback(state,{from:'2026-11-27',until:'2026-11-27'}).length,2);
 const selected=selectFeedback(state,{course:c.id,search:'ИВАН'});assert.equal(selected.length,2);assert.equal(summarizeFeedback(selected)[0].average,4.5);
 assert.equal(selectFeedback(state,{search:'missing'}).length,0);assert.equal(state.enrollments.length,4);
});
test('CSV protects formulas and preserves multiline quoted comments with BOM',async()=>{
 const {feedbackCsv}=await import(modulePath);const csv=feedbackCsv([{course:{title:'=1+1'},person:{name:'Иван'},feedback:{rating:5,liked:'Текст; "цитата"\nстрока',improvements:'\t=1+1',submittedAt:'2026-11-27T12:00:00Z'}}]);
 assert.ok(csv.startsWith('\uFEFF'));assert.ok(csv.includes('"\'=1+1"'));assert.ok(csv.includes('"\'\t=1+1"'));assert.ok(csv.includes('"Текст; ""цитата""\nстрока"'));assert.ok(csv.endsWith('\r\n'));
});
