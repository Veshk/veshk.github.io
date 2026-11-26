const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const content = require('../lk/real-course-content.js');

test('public course data only opens local examples for lessons, meetings and materials', async () => {
  const {recordedContent} = await import('../lk/free-courses/course-content.mjs');
  const allowed = new Set(['veshk.github.io', 'veshk.ru', 'static.tildacdn.com', 'youtu.be']);
  function visit(value) {
    if (typeof value === 'string' && /^https?:/.test(value)) {
      assert.ok(allowed.has(new URL(value).hostname), 'Unexpected external resource host');
    } else if (value && typeof value === 'object') {
      Object.values(value).forEach(visit);
    }
  }
  visit(content); visit(recordedContent);
  assert.doesNotMatch(JSON.stringify(content), /(?:код доступа|идентификатор конференции)\s*[:=]\s*[0-9]/i);
  for (const course of Object.values(recordedContent)) {
    assert.ok(course.lessons.length > 0);
    for (const lesson of course.lessons) {
      assert.equal(new URL(lesson.url).pathname, '/lk/demo-resource.html');
      assert.equal(lesson.provider, 'Демонстрация');
    }
  }
});

test('public demo does not load legacy browser snapshots with working links', () => {
  for (const [file, oldKey, newKey] of [
    ['lk/demo.js', 'vesh-portal-demo-v1', 'vesh-portal-demo-public-v2'],
    ['lk/free-courses/app.mjs', 'vesh.free-courses.prototype.v1', 'vesh.free-courses.public.v2'],
  ]) {
    const code = fs.readFileSync(path.join(root, file), 'utf8');
    assert.ok(!code.includes(`'${oldKey}'`));
    assert.ok(code.includes(`'${newKey}'`));
  }
});
