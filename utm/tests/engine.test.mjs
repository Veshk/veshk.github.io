import test from 'node:test';
import assert from 'node:assert/strict';
import {createRequire} from 'node:module';
import {slug,contentDate,campaignName,parseAddress,buildLink,csv} from '../engine.mjs';
const f={source:'telegram',medium:'social',campaign:'materials_mk_upravlenie',content:'28.10.2026',term:''};
test('Russian names become readable lower-case campaign labels',()=>{
  assert.equal(slug('  Управление командой — Ёж & HR  '),'upravlenie_komandoy_yozh_hr');
  assert.equal(campaignName('materials','Управление командой','2026-10-28'),'materials_mk_upravlenie_komandoy');
  assert.equal(campaignName('digest','HR','2026-10-28'),'digest_hr_2026_10');
  assert.equal(campaignName('materials','🎉','2026-10-28'),'');
});
test('date respects local calendar, four-digit year and leap days',()=>{
  assert.equal(contentDate('2026-10-28'),'28.10.2026');assert.equal(contentDate('2024-02-29'),'29.02.2024');
  assert.equal(contentDate('2026-02-29'),'');assert.equal(contentDate('2026-13-01'),'');assert.equal(contentDate(''),'');
});
test('adds HTTPS and produces exactly the chosen tags',()=>{
  const result=buildLink('  veshk.ru/calendar  ',f);assert.equal(result.url.protocol,'https:');
  assert.equal(result.href,'https://veshk.ru/calendar?utm_source=telegram&utm_medium=social&utm_campaign=materials_mk_upravlenie&utm_content=28.10.2026');
  assert.ok(!result.href.endsWith('&'));assert.ok(!result.href.includes('utm_term'));
});
test('replaces old duplicate and case-variant tags while preserving page parameters and anchor',()=>{
  const r=buildLink('https://veshk.ru/course?ref=partner&coupon=A%2BB&utm_source=vk&utm_source=email&UTM_MEDIUM=cpc&utm_term=old&utm_custom=old#price',f);
  assert.equal(r.url.searchParams.get('ref'),'partner');assert.equal(r.url.searchParams.get('coupon'),'A+B');
  assert.equal(r.url.hash,'#price');assert.deepEqual(r.url.searchParams.getAll('utm_source'),['telegram']);
  assert.equal(r.url.searchParams.has('utm_term'),false);assert.equal(r.url.searchParams.has('UTM_MEDIUM'),false);assert.equal(r.url.searchParams.has('utm_custom'),false);
});
test('empty optional fields disappear and manual values are encoded without changing their meaning',()=>{
  const r=buildLink('https://veshk.ru/?ref=1',{...f,campaign:'HR & продажи',content:'',term:'C++ / "a" #b'});
  assert.equal(r.url.searchParams.get('utm_campaign'),'HR & продажи');assert.equal(r.url.searchParams.get('utm_term'),'C++ / "a" #b');assert.equal(r.url.searchParams.has('utm_content'),false);
});
test('rejects broken double-question-mark links present in the old table',()=>{
  assert.throws(()=>buildLink('https://veshk.ru/?utm_source=telegram&utm_content=25.02.25?utm_source=vk',f),/несколько/);
});
test('rejects script URLs, credentials, whitespace, malformed URL and missing required fields',()=>{
  for(const input of ['javascript:alert(1)','data:text/html,test','ftp://veshk.ru/a','https://login:password@veshk.ru/','https://vesh k.ru','not-a-url','https://'])assert.throws(()=>parseAddress(input));
  for(const key of ['source','medium','campaign'])assert.throws(()=>buildLink('https://veshk.ru',{...f,[key]:''}));
});
test('protocol-relative and international URLs remain valid',()=>{
  assert.equal(buildLink('//veshk.ru/a',f).url.protocol,'https:');
  assert.equal(buildLink('https://пример.рф/курс',f).url.hostname,'xn--e1afmkfd.xn--p1ai');
});
test('CSV retains line breaks and quotes, has a BOM, and prevents spreadsheet formula execution',()=>{
  const out=csv([['Название','Ссылка'],['=HYPERLINK("a")','https://veshk.ru/?a=1&b=2'],['line\nnext',' @SUM(1)']]);
  assert.ok(out.startsWith('\uFEFF'));assert.ok(out.includes('"\'=HYPERLINK(""a"")"'));assert.ok(out.includes('"\' @SUM(1)"'));assert.ok(out.includes('"line\nnext"'));
});
test('offline QR renderer accepts full URL with a four-module quiet zone',()=>{
  const require=createRequire(import.meta.url);const qrcode=require('../vendor/qrcode.js');const qr=qrcode(0,'M');qr.addData(buildLink('https://veshk.ru/calendar',f).href);qr.make();
  assert.ok(qr.getModuleCount()>20);const svg=qr.createSvgTag({cellSize:5,margin:20});assert.ok(svg.includes('<svg'));assert.ok(svg.includes('width="'+(qr.getModuleCount()*5+40)+'px"'));
});
