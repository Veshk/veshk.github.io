import {slug,localDate,contentDate,campaignName,parseAddress,buildLink,csv} from './engine.mjs';
import {channels,sources,campaignTypes} from './data.mjs';
const $ = id => document.getElementById(id);
const icons = {
  link:'<path d="m10 13 4-4M8 16l-1 1a4 4 0 0 1-6-6l5-5a4 4 0 0 1 6 0M16 8l1-1a4 4 0 0 1 6 6l-5 5a4 4 0 0 1-6 0" transform="translate(0 0) scale(.95)"/>',
  copy:'<rect x="8" y="8" width="12" height="13" rx="2"/><path d="M15 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h3"/>',
  clock:'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
  mail:'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>',
  send:'<path d="m21 3-7 18-4-7-7-4 18-7ZM10 14 21 3"/>',
  vk:'<path d="M3 6c0 7 4 12 10 12h1v-5c3 0 5 3 6 5h2c-1-3-3-6-6-7 3-1 5-4 5-6h-3c-1 3-3 5-4 5V5h-3v9C8 13 6 10 6 6H3Z" stroke-width="1.3"/>',
  presentation:'<rect x="3" y="3" width="18" height="13" rx="1"/><path d="M12 16v5m-5 0 5-3 5 3M7 11l3-3 3 2 4-4"/>',
  qr:'<path d="M3 3h6v6H3zM15 3h6v6h-6zM3 15h6v6H3zM15 15h3v3h-3zM21 15v6h-6M12 3v3M3 12h3m6-3v6m-3-3h9m3 0h1M12 18v3"/>',
  grid:'<rect x="3" y="3" width="6" height="6" rx="1"/><rect x="15" y="3" width="6" height="6" rx="1"/><rect x="3" y="15" width="6" height="6" rx="1"/><path d="M18 14v8m-4-4h8"/>',
  sliders:'<path d="M4 6h16M4 18h16M4 12h16"/><circle cx="8" cy="6" r="2" fill="white"/><circle cx="16" cy="12" r="2" fill="white"/><circle cx="10" cy="18" r="2" fill="white"/>',
  spark:'<path d="m12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5L12 3Z"/>',
  external:'<path d="M14 3h7v7M21 3 10 14M10 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-5"/>',
  bulb:'<path d="M8 16c-6-5-3-13 4-13s10 8 4 13l-1 3H9l-1-3ZM9 22h6M12 18v-7m-3 0 3 2 3-2"/>',
  shield:'<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Zm-4 9 3 3 5-6"/>',
  download:'<path d="M12 3v12m-5-5 5 5 5-5M4 15v6h16v-6"/>',
  search:'<circle cx="10" cy="10" r="7"/><path d="m15 15 6 6"/>',
  edit:'<path d="m14 5 5 5M3 21l5-1L21 7a2 2 0 0 0-5-5L3 15v6Z"/>',
  trash:'<path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7"/>'
};
const icon = name => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${icons[name] || icons.link}</svg>`;
function hydrateIcons(root = document) { root.querySelectorAll('[data-icon]').forEach(el => { el.innerHTML=icon(el.dataset.icon); }); }
function optionList(select, rows) { select.replaceChildren(...rows.map(([value,label]) => new Option(label,value))); }
const HISTORY_KEY='vesh-utm-history-v1', DRAFT_KEY='vesh-utm-draft-v1';
let overrides={}, current=null, toastTimer, history=[], addressTouched=false, qr=null;
function readStore(key, fallback) {try{return JSON.parse(localStorage.getItem(key)) ?? fallback;}catch{return fallback;}}
function writeStore(key,value) {try{localStorage.setItem(key,JSON.stringify(value));return true;}catch{return false;}}
const storedHistory = readStore(HISTORY_KEY,[]);
if (Array.isArray(storedHistory)) history=storedHistory.filter(item=>{
  try{return typeof item.id==='string' && typeof item.title==='string' && typeof item.href==='string' && item.href.length<10000 && ['http:','https:'].includes(new URL(item.href).protocol) && !new URL(item.href).username;}catch{return false;}
}).slice(0,100);

$('channels').innerHTML=channels.map((c,i)=>`<label class="channel"><input type="radio" name="channel" value="${c.id}" aria-label="${c.label}" ${i===0?'checked':''}><span class="channel-face"><span class="channel-icon" aria-hidden="true">${icon(c.icon)}</span><strong>${c.label}</strong><small>${c.hint}</small></span></label>`).join('');
optionList($('source-choice'),sources);optionList($('campaign-type'),campaignTypes);
const technicalLabels={source:'utm_source',medium:'utm_medium',campaign:'utm_campaign',content:'utm_content',term:'utm_term'};
for(const [key,label] of Object.entries(technicalLabels)){
  const row=document.createElement('div');row.className='advanced-row';
  const l=document.createElement('label');l.htmlFor=`utm-${key}`;l.textContent=label;
  const input=document.createElement('input');input.id=`utm-${key}`;input.maxLength=256;input.autocomplete='off';input.spellcheck=false;
  input.addEventListener('input',()=>{overrides[key]=input.value;update();saveDraft();});
  row.append(l,input);$('technical-fields').append(row);
}
hydrateIcons();
function channel(){return channels.find(c=>c.id===document.querySelector('[name=channel]:checked').value);}
function syncChannel(placement) {
  const c=channel();optionList($('placement'),c.options);
  if(c.options.some(([v])=>v===placement)) $('placement').value=placement;
  $('source-wrap').hidden=c.id!=='other';
  $('custom-source-wrap').hidden=c.id!=='other'||$('source-choice').value!=='custom';
  const notes={vk:'Обычный пост и реклама получат разные метки — их легко сравнить в отчёте.',telegram:'Для поста в канале и личного сообщения используем разные типы размещения.',email:'Для рассылки используем ваш источник enkod и тип трафика email.',presentation:'Для презентаций используем presentation. QR-код можно скачать справа.',qr:'Сначала создайте ссылку, затем скачайте QR-код для печатного материала.',other:'Выберите источник и формат. Если нужного источника нет, добавьте свой.'};
  $('channel-note').lastElementChild.textContent=notes[c.id];
}
function fields() {
  const c=channel();let source=c.source;
  if(c.id==='other') source=$('source-choice').value==='custom'?slug($('custom-source').value):$('source-choice').value;
  const variant=slug($('variant').value), date=contentDate($('date').value);
  return {...{source,medium:$('placement').value,campaign:campaignName($('campaign-type').value,$('theme').value,$('date').value),content:date+(variant?`_${variant}`:''),term:''},...overrides};
}
function snapshot(){return {address:$('address').value,channel:channel().id,placement:$('placement').value,source:$('source-choice').value,customSource:$('custom-source').value,type:$('campaign-type').value,theme:$('theme').value,date:$('date').value,variant:$('variant').value,overrides:{...overrides}};}
function saveDraft(){writeStore(DRAFT_KEY,snapshot());}
function restore(s){
  if(!s || typeof s!=='object')return;
  const pairs={address:'address',source:'source-choice',customSource:'custom-source',type:'campaign-type',theme:'theme',date:'date',variant:'variant'};
  for(const [key,id] of Object.entries(pairs)) if(typeof s[key]==='string') $(id).value=s[key].slice(0,10000);
  if(channels.some(c=>c.id===s.channel)) document.querySelector(`[name=channel][value="${s.channel}"]`).checked=true;
  if(!$('source-choice').value)$('source-choice').value='blog';if(!$('campaign-type').value)$('campaign-type').value='anons';
  overrides={};if(s.overrides && typeof s.overrides==='object')for(const key of Object.keys(technicalLabels))if(typeof s.overrides[key]==='string')overrides[key]=s.overrides[key].slice(0,256);
  syncChannel(s.placement);update();
}
function update(){
  const f=fields();let result=null, error='';
  try{result=buildLink($('address').value,f);if(!contentDate($('date').value) && !Object.hasOwn(overrides,'content')){result=null;error='Выберите корректную дату размещения.';}}catch(e){error=e.message;}
  let addressError='';try{parseAddress($('address').value);}catch(e){addressError=e.message;}
  $('address-error').hidden=!addressTouched||!addressError;
  $('address-error').textContent=addressError;
  $('address').setAttribute('aria-invalid',String(addressTouched&&!!addressError));
  current=result?{...result,fields:f}:null;
  for(const key of Object.keys(technicalLabels)){
    const input=$(`utm-${key}`);if(document.activeElement!==input)input.value=f[key];
    input.classList.toggle('overridden',Object.hasOwn(overrides,key));
  }
  $('copy-link').disabled=!current;$('qr-button').disabled=!current;
  $('ready-badge').textContent=current?'✓ Ссылка готова':'Заполните 3 шага';$('ready-badge').classList.toggle('ready',!!current);
  $('result-title').textContent=current?'Можно делиться':'Всё готово к началу';
  $('result-description').textContent=current?'Скопируйте ссылку и вставьте её в свой материал.':($('address').value&&$('theme').value?error:'Добавьте адрес и название материала. Ссылка появится здесь.');
  $('test-link').setAttribute('aria-disabled',String(!current));
  if(current)$('test-link').href=current.href;else $('test-link').removeAttribute('href');
  const preview=$('link-preview');preview.classList.toggle('placeholder',!current);
  if(current){
    const url=new URL(current.href);preview.replaceChildren(document.createTextNode(`${url.origin}${url.pathname}`));
    for(const [key,value] of url.searchParams){const part=document.createElement('span');part.textContent=`${preview.childNodes.length===1?'?':'&'}${encodeURIComponent(key)}=${encodeURIComponent(value)}`;preview.append(document.createElement('br'),part);}
    if(url.hash)preview.append(document.createTextNode(url.hash));
  }else{preview.innerHTML='https://veshk.ru/<span>?utm_source=…<br>&amp;utm_medium=…<br>&amp;utm_campaign=…</span>';}
  $('summary-channel').textContent=Object.hasOwn(overrides,'source')?f.source:channel().id==='other'?$('source-choice').value==='custom'?$('custom-source').value:$('source-choice').selectedOptions[0].textContent:channel().label;
  $('summary-placement').textContent=Object.hasOwn(overrides,'medium')?f.medium:$('placement').selectedOptions[0].textContent;
  $('summary-campaign').textContent=f.campaign||'Пока не задана';
  $('warnings').replaceChildren();
  const warn=text=>{const p=document.createElement('p');p.textContent=text;$('warnings').append(p);};
  if(current?.existing.length)warn('В исходной ссылке были метки. Заменили их на новые; остальные параметры сохранили.');
  if(current?.url.protocol==='http:')warn('Ссылка использует http://. Если сайт поддерживает HTTPS, замените начало адреса.');
  if(Object.keys(overrides).length)warn('Есть ручные настройки. Они имеют приоритет над выбранными шагами.');
  if(current&&/[^\x00-\x7F]/.test(Object.values(f).join('')))warn('В ручных метках есть кириллица: в ссылке она будет закодирована. Для единых отчётов обычно используют латиницу.');
  if(current&&current.href.length>1800)warn('Ссылка получилась длинной. Для удобного QR-кода сократите название и необязательные поля.');
}
function toast(message){clearTimeout(toastTimer);$('toast').textContent=message;$('toast').hidden=false;toastTimer=setTimeout(()=>{$('toast').hidden=true;},4500);}
async function copyText(text){
  try{await navigator.clipboard.writeText(text);return true;}catch{
    const area=document.createElement('textarea');area.value=text;area.style.cssText='position:fixed;left:-9999px';document.body.append(area);area.select();let ok=false;try{ok=document.execCommand('copy');}catch{}area.remove();return ok;
  }
}
function saveCurrent(entry){
  const old=history.findIndex(h=>h.href===entry.href);if(old!==-1)history.splice(old,1);
  history.unshift(entry);history=history.slice(0,100);const saved=writeStore(HISTORY_KEY,history);renderHistory();return saved;
}
function entryFromCurrent(){return {id:crypto.randomUUID?crypto.randomUUID():String(Date.now()),href:current.href,title:$('theme').value.trim()||current.fields.campaign,channel:$('summary-channel').textContent,placement:$('summary-placement').textContent,campaign:current.fields.campaign,savedAt:new Date().toISOString(),form:snapshot()};}
$('copy-link').addEventListener('click',async()=>{
  if(!current)return;const entry=entryFromCurrent();
  const ok=await copyText(entry.href);if(!ok){toast('Браузер не разрешил копирование. Выделите ссылку в блоке выше и скопируйте вручную.');return;}
  const saved=saveCurrent(entry);toast(saved?'Ссылка скопирована и сохранена в «Мои ссылки»':'Ссылка скопирована. Браузер не разрешил сохранить историю.');
});
function showView(view){const isCreate=view==='create';$('create-view').hidden=!isCreate;$('history-view').hidden=isCreate;for(const id of ['create','history']){$(`${id}-tab`).classList.toggle('active',id===view);if(id===view)$(`${id}-tab`).setAttribute('aria-current','page');else $(`${id}-tab`).removeAttribute('aria-current');}if(!isCreate)renderHistory();}
$('create-tab').addEventListener('click',()=>showView('create'));$('history-tab').addEventListener('click',()=>showView('history'));
function renderHistory(){
  $('history-count').textContent=history.length;$('export-history').disabled=!history.length;$('clear-history').disabled=!history.length;
  const list=$('history-list');list.replaceChildren();const search=$('history-search').value.toLowerCase().trim();
  const items=history.filter(item=>[item.title,item.channel,item.href].join(' ').toLowerCase().includes(search));
  if(!items.length){const empty=document.createElement('div');empty.className='empty-history';empty.innerHTML=`<span>${icon(history.length?'search':'link')}</span><h3>${history.length?'Ничего не нашлось':'Здесь будут ваши ссылки'}</h3><p>${history.length?'Попробуйте другое название или площадку.':'Создайте и скопируйте первую ссылку — мы сохраним её здесь.'}</p>`;if(!history.length){const b=document.createElement('button');b.className='secondary-button';b.textContent='Создать первую ссылку';b.onclick=()=>showView('create');empty.append(b);}list.append(empty);return;}
  for(const item of items){
    const article=document.createElement('article');article.className='history-item';
    const main=document.createElement('div'),title=document.createElement('h3'),meta=document.createElement('div'),url=document.createElement('p');title.textContent=item.title;meta.className='history-meta';url.className='history-url';url.textContent=item.href;
    const d=new Date(item.savedAt);meta.textContent=`${item.channel||'Ссылка'} · ${Number.isNaN(d.getTime())?'':d.toLocaleDateString('ru-RU')} · ${item.placement||''}`;
    main.append(title,meta,url);const actions=document.createElement('div');actions.className='history-buttons';
    const button=(label,ic,fn)=>{const b=document.createElement('button');b.type='button';b.innerHTML=icon(ic);b.append(document.createTextNode(label));b.onclick=fn;actions.append(b);return b;};
    button('Копировать','copy',async()=>toast(await copyText(item.href)?'Ссылка скопирована':'Не удалось скопировать. Скопируйте текст ссылки вручную.'));
    if(item.form)button('Повторить','edit',()=>{restore(item.form);saveDraft();showView('create');$('address').focus();toast('Параметры восстановлены. Можно изменить их и создать новую ссылку.');});
    const del=button('','trash',()=>{const removed=item;history=history.filter(h=>h.id!==item.id);writeStore(HISTORY_KEY,history);renderHistory();clearTimeout(toastTimer);$('toast').replaceChildren(document.createTextNode('Ссылка удалена. '));const undo=document.createElement('button');undo.textContent='Отменить';undo.style.cssText='color:white;text-decoration:underline';undo.onclick=()=>{saveCurrent(removed);toast('Ссылка восстановлена');};$('toast').append(undo);$('toast').hidden=false;toastTimer=setTimeout(()=>{$('toast').hidden=true;},7000);});del.setAttribute('aria-label',`Удалить ссылку «${item.title}»`);
    article.append(main,actions);list.append(article);
  }
}
$('history-search').addEventListener('input',renderHistory);
function download(data,name,type){const blob=new Blob([data],{type});const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download=name;document.body.append(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),1000);}
$('export-history').addEventListener('click',()=>{const rows=[['Дата сохранения','Название','Источник','Размещение','Кампания','Ссылка'],...history.map(h=>[h.savedAt,h.title,h.channel,h.placement,h.campaign,h.href])];download(csv(rows),`vesh-links-${localDate()}.csv`,'text/csv;charset=utf-8');toast('Файл с историей подготовлен');});
$('clear-history').addEventListener('click',()=>$('clear-dialog').showModal());$('confirm-clear').addEventListener('click',()=>{history=[];writeStore(HISTORY_KEY,history);renderHistory();$('clear-dialog').close();toast('История очищена');});
function makeQR(){
  if(!current)return;
  try{qr=window.qrcode(0,'M');qr.addData(current.href,'Byte');qr.make();$('qr-image').innerHTML=qr.createSvgTag({cellSize:5,margin:20,scalable:true});$('qr-image').querySelector('svg').setAttribute('aria-label','QR-код готовой ссылки');$('qr-image').querySelector('svg').setAttribute('role','img');$('qr-dialog').showModal();}catch{qr=null;toast('Ссылка слишком длинная для QR-кода. Сократите название или необязательные метки.');}
}
$('qr-button').addEventListener('click',makeQR);
$('download-svg').addEventListener('click',()=>{if(qr)download(qr.createSvgTag({cellSize:8,margin:32}),`vesh-${current.fields.campaign}.svg`,'image/svg+xml');});
$('download-png').addEventListener('click',()=>{
  if(!qr)return;const count=qr.getModuleCount(),cell=12,quiet=4;const canvas=document.createElement('canvas');canvas.width=canvas.height=(count+quiet*2)*cell;
  const context=canvas.getContext('2d');context.fillStyle='#fff';context.fillRect(0,0,canvas.width,canvas.height);context.fillStyle='#000';
  for(let r=0;r<count;r++)for(let c=0;c<count;c++)if(qr.isDark(r,c))context.fillRect((c+quiet)*cell,(r+quiet)*cell,cell,cell);
  const name=`vesh-${current.fields.campaign}.png`;canvas.toBlob(blob=>{if(blob)download(blob,name,'image/png');else toast('Не удалось скачать PNG. Попробуйте SVG.');},'image/png');
});
for(const el of document.querySelectorAll('[data-close]'))el.addEventListener('click',()=>el.closest('dialog').close());
for(const id of ['help','show-example'])$(id).addEventListener('click',()=>$('help-dialog').showModal());
$('generator').addEventListener('submit',event=>event.preventDefault());
$('generator').addEventListener('input',event=>{if(event.target.name==='channel'){syncChannel();}if(event.target.id==='source-choice')syncChannel($('placement').value);update();saveDraft();});
$('address').addEventListener('blur',()=>{addressTouched=!!$('address').value;update();});
document.querySelectorAll('[data-url]').forEach(b=>b.addEventListener('click',()=>{$('address').value=b.dataset.url;addressTouched=true;update();saveDraft();}));
$('reset-overrides').addEventListener('click',()=>{overrides={};update();saveDraft();toast('Автозаполнение восстановлено');});
$('reset-form').addEventListener('click',()=>{$('generator').reset();$('date').value=localDate();overrides={};addressTouched=false;$('advanced').open=false;syncChannel();update();saveDraft();$('address').focus();toast('Можно создавать новую ссылку');});
$('date').value=localDate();syncChannel();restore(readStore(DRAFT_KEY,null));update();renderHistory();
window.addEventListener('storage',e=>{if(e.key===HISTORY_KEY){const next=readStore(HISTORY_KEY,[]);if(Array.isArray(next)){history=next.filter(x=>x&&typeof x.id==='string'&&typeof x.href==='string'&&typeof x.title==='string').slice(0,100);renderHistory();}}});
