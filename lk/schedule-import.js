'use strict';
(function (root) {
  const MAX_BYTES=2000000,MAX_ROWS=2000;
  const names={date:['дата'],time:['время','время екб','время по екатеринбургу'],title:['тема','занятие','название занятия'],teacher:['преподаватель'],source_id:['id','id занятия','код занятия'],group:['тег группы','тэг группы'],module:['модуль'],format:['формат','формат занятия'],url:['ссылка','ссылка на занятие','ссылка для подключения'],hours:['часы','ак. часы']};
  const norm=s=>String(s??'').replace(/^\uFEFF/,'').trim().toLowerCase().replace(/\s+/g,' ');
  function read(text,delimiter){
    const rows=[];let row=[],cell='',quoted=false;
    for(let i=0;i<text.length;i++){
      const c=text[i];
      if(c==='"'&&(quoted||cell==='')){if(quoted&&text[i+1]==='"'){cell+='"';i++;}else quoted=!quoted;}
      else if(!quoted&&(c===delimiter||c==='\n'||c==='\r')){row.push(cell);cell='';if(c!==delimiter){if(c==='\r'&&text[i+1]==='\n')i++;rows.push(row);row=[];}}
      else cell+=c;
      if(rows.length>MAX_ROWS+50||row.length>60||cell.length>10000)throw Error('Слишком большая таблица. Не более 2000 занятий и 60 колонок.');
    }
    if(quoted)throw Error('Незакрытые кавычки в CSV. Скачайте файл из Google Таблицы ещё раз.');
    if(cell!==''||row.length){row.push(cell);rows.push(row);}return rows;
  }
  function date(value){
    const s=String(value).trim();let y,m,d,match;
    if((match=s.match(/^(\d{4})-(\d{2})-(\d{2})$/))){[,y,m,d]=match;}
    else if((match=s.match(/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/))){[,d,m,y]=match;}
    else return null;
    const iso=`${y}-${m.padStart(2,'0')}-${d.padStart(2,'0')}`,v=new Date(iso+'T12:00:00Z');
    return Number(y)>=1900&&Number(y)<=2200&&!Number.isNaN(v.valueOf())&&v.toISOString().slice(0,10)===iso?iso:null;
  }
  const safeUrl=s=>{try{const u=new URL(s);return ['https:','http:'].includes(u.protocol)&&!u.username&&!u.password?u.href:null;}catch{return null;}};
  function parse(text,groupTag=''){
    if(new TextEncoder().encode(text).length>MAX_BYTES)throw Error('Таблица больше 2 МБ. Выберите только лист нужной группы.');
    if(/^\s*</.test(text))throw Error('Вместо таблицы получена страница входа. Вставьте диапазон или загрузите CSV.');
    let rows,header,map;
    for(const delimiter of ['\t',',',';']){
      const candidate=read(text.replace(/^\uFEFF/,''),delimiter);
      const index=candidate.slice(0,30).findIndex(r=>['date','time','title'].every(k=>r.some(c=>names[k].includes(norm(c)))));
      if(index>=0){rows=candidate;header=index;map={};for(const [key,aliases] of Object.entries(names)){const col=rows[index].findIndex(c=>aliases.includes(norm(c)));if(col>=0)map[key]=col;}break;}
    }
    if(!rows)throw Error('Не найдены заголовки «Дата», «Время» и «Тема» (или «Занятие») в первых 30 строках.');
    const lessons=[],errors=[],warnings=[],ids=new Set(),identities=new Set();let skipped=0;
    for(let i=header+1;i<rows.length;i++){
      const row=rows[i];if(row.every(c=>!c.trim()))continue;
      const get=k=>String(row[map[k]]??'').trim();
      if(map.group!==undefined&&get('group')!==groupTag){skipped++;continue;}
      if(norm(get('date'))==='дата')continue;
      if(!['date','time','title','teacher'].some(k=>get(k)))continue;
      const line=i+1,entry={date:date(get('date')),title:get('title'),teacher:get('teacher')};
      const time=get('time').match(/^([01]?\d|2[0-3])[:.]([0-5]\d)\s*[-–—]\s*([01]?\d|2[0-3])[:.]([0-5]\d)(?:\s+(.*))?$/);
      if(!entry.date)errors.push(`Строка ${line}: нужна точная дата ДД.ММ.ГГГГ.`);
      if(!time)errors.push(`Строка ${line}: время в формате 10:00–19:00 (ЕКБ).`);
      else{
        entry.time=`${time[1].padStart(2,'0')}:${time[2]}–${time[3].padStart(2,'0')}:${time[4]}`;
        if(Number(time[3])*60+Number(time[4])<=Number(time[1])*60+Number(time[2]))errors.push(`Строка ${line}: окончание должно быть позже начала в тот же день.`);
        if(time[5]){if(!entry.title){entry.title=time[5];warnings.push(`Строка ${line}: тема перенесена из ячейки времени. Проверьте её.`);}else errors.push(`Строка ${line}: лишний текст в ячейке времени.`);}
      }
      if(!entry.title)errors.push(`Строка ${line}: не указана тема занятия.`);
      for(const key of ['source_id','module','format','url','hours'])if(map[key]!==undefined)entry[key]=get(key);
      if(map.source_id!==undefined){if(!entry.source_id)errors.push(`Строка ${line}: заполните ID занятия.`);else if(ids.has(entry.source_id))errors.push(`Строка ${line}: повтор ID ${entry.source_id}.`);ids.add(entry.source_id);}
      if(entry.url&&!safeUrl(entry.url))errors.push(`Строка ${line}: ссылка должна начинаться с https:// или http://.`);
      if(entry.hours&&!/^\d{1,3}(?:[.,]\d{1,2})?$/.test(entry.hours))errors.push(`Строка ${line}: часы должны быть числом.`);
      for(const [key,limit] of Object.entries({title:300,teacher:180,module:180,format:60,url:2000,source_id:100}))if((entry[key]||'').length>limit)errors.push(`Строка ${line}: слишком длинное значение ${key}.`);
      const identity=[entry.date,entry.time,entry.title,entry.teacher].join('|');
      if(identities.has(identity))errors.push(`Строка ${line}: занятие повторяется.`);identities.add(identity);
      lessons.push(entry);
    }
    if(lessons.length>MAX_ROWS)errors.push('Не более 2000 занятий за одно обновление.');
    if(!lessons.length)errors.push(map.group!==undefined?'Нет занятий с ТЕГом этой группы. Проверьте ТЕГ в источнике.':'В таблице нет занятий. Пустой импорт не удаляет расписание.');
    if(map.source_id===undefined)warnings.push('Нет колонки «ID занятия». При изменении даты, времени, темы или преподавателя строка считается новой. Для сохранения её ручных дополнений добавьте постоянный ID.');
    return {lessons,errors,warnings,header:header+1,skipped,columns:Object.keys(map)};
  }
  const key=row=>row.source_id?'id:'+row.source_id:JSON.stringify([row.date,row.time?.replace(/\s/g,'').replace(/[-—]/g,'–').replace(/\./g,':'),row.title,row.teacher||'']);
  function merge(parsed,current,mode='replace'){
    if(!['replace','append'].includes(mode))throw Error('Выберите способ обновления расписания.');
    if(mode==='append'){
      const identity=r=>JSON.stringify([r.date,r.time?.replace(/\s/g,'').replace(/[-—]/g,'–').replace(/\./g,':'),r.title,r.teacher||'']);
      const keys=new Set(current.map(key)),identities=new Set(current.map(identity));
      const added=parsed.lessons.filter(r=>!keys.has(key(r))&&!identities.has(identity(r)));
      const lessons=[...current,...added].sort((a,b)=>(a.date||'').localeCompare(b.date||'')||(a.time||'').localeCompare(b.time||''));
      const errors=[...parsed.errors];if(lessons.length>MAX_ROWS)errors.push('В блоке может быть не более 2000 занятий.');
      return {...parsed,errors,mode,lessons,counts:{added:added.length,changed:0,same:parsed.lessons.length-added.length,removed:0},removed:[]};
    }
    const old=new Map(current.map(row=>[key(row),row]));let added=0,changed=0,same=0;
    const lessons=parsed.lessons.map(row=>{const match=old.get(key(row));old.delete(key(row));if(!match)added++;else if(Object.keys(row).some(k=>String(row[k]??'')!==String(match[k]??'')))changed++;else same++;return {...(match||{}),...row};});
    return {...parsed,mode,lessons,counts:{added,changed,same,removed:old.size},removed:[...old.values()]};
  }
  function sourceUrl(value){
    let u;try{u=new URL(value);}catch{throw Error('Вставьте ссылку на Google Таблицу.');}
    if(u.protocol!=='https:'||u.hostname!=='docs.google.com'||u.port||u.username||u.password)throw Error('Нужна https-ссылка docs.google.com/spreadsheets/.');
    const match=u.pathname.match(/^\/spreadsheets\/d\/(e\/)?([A-Za-z0-9_-]+)(?:\/[^/]*)?\/?$/);
    if(!match)throw Error('Не удалось распознать ссылку Google Таблицы.');
    const gid=u.searchParams.get('gid')||new URLSearchParams(u.hash.slice(1)).get('gid')||'0';
    if(!/^\d+$/.test(gid))throw Error('Неверный номер листа gid.');
    return `https://docs.google.com/spreadsheets/d/${match[1]||''}${match[2]}/${match[1]?'pub?output=csv':'export?format=csv'}&gid=${gid}`;
  }
  async function fetchSource(value){
    const response=await fetch(sourceUrl(value),{credentials:'omit',signal:AbortSignal.timeout(15000)});
    if(!response.ok)throw Error('Источник недоступен. Для закрытой таблицы вставьте диапазон или загрузите CSV.');
    const reader=response.body.getReader(),chunks=[];let size=0;
    try{while(true){const {done,value}=await reader.read();if(done)break;size+=value.length;if(size>MAX_BYTES)throw Error('Источник больше 2 МБ. Выберите только лист группы.');chunks.push(value);}}finally{await reader.cancel();}
    const result=new Uint8Array(size);let offset=0;for(const c of chunks){result.set(c,offset);offset+=c.length;}return new TextDecoder().decode(result);
  }
  const api={parse,merge,sourceUrl,fetchSource};
  if(typeof module!=='undefined'&&module.exports)module.exports=api;else root.ScheduleImport=api;
})(typeof window==='undefined'?{}:window);
