'use strict';
window.AdminSchedules={init(ctx){
  const esc=ctx.escape;
  const button=document.createElement('button');button.dataset.panel='schedules';button.textContent='Расписание';document.querySelector('[data-panel="groups"]').after(button);
  const panel=document.createElement('section');panel.id='schedules-panel';panel.hidden=true;document.querySelector('#groups-panel').after(panel);
  panel.innerHTML=`<div class="group-workspace"><div class="group-filters"><label class="field">Поиск<input id="schedule-search" type="search" placeholder="Группа, тема или преподаватель"></label><label class="field">Группа<select id="schedule-group"><option value="">Все группы</option></select></label><label class="field">Администратор<select id="schedule-owner"><option value="">Все администраторы</option>${Object.entries(ctx.names).map(([id,name])=>`<option value="${esc(id)}">${esc(name)}</option>`).join('')}</select></label><label class="field">Показывать<select id="schedule-scope"><option value="">Все занятия</option><option value="future">Предстоящие</option><option value="past">Прошедшие</option></select></label></div><div class="group-toolbar"><label class="check"><input type="checkbox" id="schedule-mine">Только мои группы</label><span id="schedule-count" role="status"></span></div><div id="schedule-groups"></div></div><p class="form-note">Откройте группу, чтобы увидеть расписание в её ЛК. «Править таблицей» позволяет скопировать все строки, отредактировать их и проверить полную замену. «Добавить из таблицы» сохраняет существующие занятия.</p>`;
  const value=id=>panel.querySelector('#'+id).value;
  const today=()=>new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Yekaterinburg',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
  function render(){
    const select=panel.querySelector('#schedule-group'),selected=select.value;select.innerHTML='<option value="">Все группы</option>'+Object.entries(ctx.groups).map(([id,g])=>`<option value="${esc(id)}">${esc(g.code)} — ${esc(g.name)}</option>`).join('');select.value=selected;
    const search=value('schedule-search').trim().toLocaleLowerCase('ru'),scope=value('schedule-scope');let count=0,groupCount=0;
    panel.querySelector('#schedule-groups').innerHTML=Object.entries(ctx.groups).map(([id,g])=>{
      if(value('schedule-group')&&value('schedule-group')!==id||value('schedule-owner')&&value('schedule-owner')!==g.id||panel.querySelector('#schedule-mine').checked&&g.id!==ctx.role)return '';
      const blocks=CoursePages.getBlocks(g).filter(b=>b.type==='schedule');
      const matchGroup=(g.name+' '+g.code+' '+g.owner).toLocaleLowerCase('ru').includes(search);
      const shown=blocks.map(b=>({...b,data:{...b.data,collapse_past:false,lessons:(b.data.lessons||[]).filter(l=>(matchGroup||(l.title+' '+l.teacher).toLocaleLowerCase('ru').includes(search))&&(!scope||(scope==='future'?l.date>=today():l.date<today())))}}));
      const n=shown.reduce((sum,b)=>sum+b.data.lessons.length,0);if((search||scope)&&!n&&!(!scope&&matchGroup))return '';
      count+=n;groupCount++;
      const all=blocks.reduce((sum,b)=>sum+(b.data.lessons||[]).length,0);
      return `<details class="schedule-group" ${value('schedule-group')?'open':''}><summary><span><strong>${esc(g.name)}</strong><small>${esc(g.code)} ${esc(g.owner)}</small></span><span>${n} из ${all} занятий${ctx.canEditGroup(id)?'':' Только просмотр'}</span></summary><div class="schedule-group-body">${shown.map(b=>`<div class="schedule-block-heading"><h3>${esc(b.data.title)}${b.data.visible===false?' Скрыто в ЛК':''}</h3>${ctx.canEditGroup(id)?`<div class="group-row-actions"><button type="button" class="btn outline" data-schedule-edit="${esc(id)}" data-block="${esc(b.data.block_id)}">Править таблицей</button><button type="button" class="btn" data-schedule-add="${esc(id)}" data-block="${esc(b.data.block_id)}">Добавить из таблицы</button></div>`:''}</div>${CoursePages.schedule(b.data,g)}`).join('')||'<p>Блок расписания ещё не добавлен.</p>'}<div class="group-row-actions"><a class="text-link" href="${ctx.groupLink(id)}&preview=1&role=${ctx.role}" target="_blank" rel="noopener">Открыть ЛК группы ↗</a><a class="text-link" href="editor.html?group=${encodeURIComponent(id)}&role=${ctx.role}">${ctx.canEditGroup(id)?'Редактор страницы':'Посмотреть страницу'} →</a></div></div></details>`;
    }).join('')||'<div class="empty">По этим условиям расписаний нет.</div>';
    panel.querySelector('#schedule-count').textContent=`Групп: ${groupCount} занятий: ${count}`;
  }
  panel.querySelectorAll('input,select').forEach(el=>el.addEventListener(el.type==='search'?'input':'change',render));
  panel.addEventListener('click',e=>{
    const b=e.target.closest('[data-schedule-edit],[data-schedule-add]');if(!b)return;
    const id=b.dataset.scheduleEdit||b.dataset.scheduleAdd;if(!ctx.canEditGroup(id))return;
    const group=ctx.groups[id],blocks=CoursePages.getBlocks(group),data=blocks.find(block=>block.data.block_id===b.dataset.block)?.data;if(!data)return;
    const snapshot=JSON.stringify(group.page_blocks);
    const fields=[['date','Дата'],['time','Время'],['title','Тема'],['teacher','Преподаватель'],['hours','Часы'],['module','Модуль'],['format','Формат'],['url','Ссылка']];
    if(data.lessons?.length&&data.lessons.every(l=>l.source_id))fields.unshift(['source_id','ID занятия']);
    const cell=s=>'"'+String(s??'').replace(/"/g,'""')+'"';
    const initialText=b.dataset.scheduleEdit?[fields.map(f=>cell(f[1])).join('\t'),...(data.lessons||[]).map(l=>fields.map(f=>cell(l[f[0]])).join('\t'))].join('\n'):'';
    ScheduleImportUI.open({data,group,initialText,initialMode:b.dataset.scheduleEdit?'replace':'append',applyLabel:'Сохранить расписание',saveHint:'Изменения сохранятся в расписании выбранной группы.',onApply(lessons,source){
      if(snapshot!==JSON.stringify(group.page_blocks)){alert('Расписание изменилось. Откройте таблицу ещё раз.');return false;}
      const previous=group.page_blocks;data.lessons=lessons;data.source_url=source;group.page_blocks=blocks;
      ctx.state.groupOverrides[id]={...group};if(!ctx.save()){group.page_blocks=previous;ctx.state.groupOverrides[id]={...group};return false;}render();return true;
    }});
  });
  render();return {render};
}};
