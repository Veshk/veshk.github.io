const test = require('node:test');
const assert = require('node:assert/strict');
const {create} = require('../student-directory.js');

test('counts unique enrolled accounts, keeps expired enrollments, and excludes pending requests', () => {
  const requests = [
    {id:1,name:'New',email:'new@example.test',status:'pending',group:'a'},
    {id:2,name:'Same',email:' STUDENT-1@vesh.test ',status:'approved',group:'a'},
    {id:3,name:'Same',email:'student-1@vesh.test',status:'approved',group:'b'},
    {id:4,name:'Gone',email:'gone@example.test',status:'approved',group:'missing'},
  ];
  const directory=create({groups:{a:{until:'2026-11-04'},b:{until:'2027-01-01'},empty:{until:'2027-01-01'}},sampleGroups:{a:{}},requests,closes:g=>g.until,today:()=> '2026-11-06'});
  assert.equal(directory.participants('a').length,1);
  assert.equal(directory.participants('b').length,1);
  assert.equal(directory.participants('empty').length,0);
  const shared=directory.participants('a')[0];
  assert.deepEqual(shared.groups,['a','b']);
  assert.equal(shared.status,'active');
  assert.equal(directory.statusFor(shared,'a'),'closed');
  assert.equal(directory.statusFor(shared,'b'),'active');
  requests[0].status='approved';
  assert.equal(directory.participants('a').length,2);
  assert.equal(directory.participants('a').filter(p=>p.email==='new@example.test').length,1);
});

test('access stays open through the end date in the current group', () => {
  const directory=create({groups:{a:{until:'2026-11-06'}},sampleGroups:{a:{}},requests:[],closes:g=>g.until,today:()=> '2026-11-06'});
  assert.equal(directory.participants('a')[0].status,'active');
});
