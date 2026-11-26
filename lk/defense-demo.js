'use strict';
window.DefenseDemo = (() => {
  const key = 'vesh-defense-bookings-v1';
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const read = () => JSON.parse(localStorage.getItem(key) || '{}');
  const windows = data => data.windows || (data.days || []).flatMap(day => (day.slots || []).map(slot => ({...slot,date:day.date})));
  const preview = data => `<p class="defense-note">Время Екатеринбурга · одно окно — одна защита, 1–2 участника</p>${windows(data).map(s => `<div class="defense-slot"><strong>${esc(s.date)}</strong><span>${esc(s.start)}–${esc(s.end)}</span></div>`).join('') || '<div class="empty">Даты защиты пока не опубликованы.</div>'}`;
  function prepare(blocks, groupId) {
    const result = structuredClone(blocks), booked = read()[groupId] || {};
    for (const block of result.filter(b => b.type === 'defense')) {
      const list = windows(block.data).map(s => ({...s,id:s.id || crypto.randomUUID()})).sort((a,b) => (a.date+a.start).localeCompare(b.date+b.start));
      let previous = null;
      const ids = new Set();
      for (const slot of list) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(slot.date) || !/^([01]\d|2[0-3]):[0-5]\d$/.test(slot.start) || !/^([01]\d|2[0-3]):[0-5]\d$/.test(slot.end) || slot.start >= slot.end || ids.has(slot.id) || previous?.date === slot.date && slot.start < previous.end) throw Error('Проверьте окна защиты: нужны дата и время; окна не должны пересекаться.');
        ids.add(slot.id); previous = slot;
      }
      block.data.days = [...new Set(list.map(s => s.date))].map(date => ({date,slots:list.filter(s => s.date === date).map(({date,...s}) => s)}));
      delete block.data.windows;
    }
    for (const [blockId,bookings] of Object.entries(booked)) for (const booking of bookings) {
      const block = result.find(b => b.type === 'defense' && b.data.block_id === blockId);
      const slot = block && windows(block.data).find(s => s.id === booking.slot_id);
      if (!slot || slot.date !== booking.date || slot.start !== booking.start || slot.end !== booking.end) throw Error('В изменённом окне защиты есть запись. Сначала перенесите или отмените её в ЛК.');
    }
    return result;
  }
  function generate(data, done) {
    const dialog = document.createElement('dialog'); dialog.className = 'defense-dialog'; dialog.setAttribute('aria-label','Создать окна защиты');
    dialog.innerHTML = '<form><h3>Создать окна по интервалу</h3><div class="defense-modal-body"><label class="field">Дата<input name="date" type="date" required></label><label class="field">Начало, ЕКБ<input name="start" type="time" value="10:00" required></label><label class="field">Длительность, минут<input name="minutes" type="number" min="5" max="240" value="45" required></label><label class="field">Число окон<input name="count" type="number" min="1" max="30" value="8" required></label><label class="field">Перерыв, минут<input name="gap" type="number" min="0" max="120" value="0" required></label></div><p role="alert"></p><div class="defense-modal-actions"><button type="button" class="btn outline">Назад</button><button type="submit" class="btn">Добавить окна</button></div></form>';
    dialog.querySelector('[type=button]').onclick = () => dialog.close();
    dialog.querySelector('form').onsubmit = event => {
      event.preventDefault(); const f = new FormData(event.target), added = [], [h,m] = f.get('start').split(':').map(Number); let start = h*60+m;
      const time = n => `${String(Math.floor(n/60)).padStart(2,'0')}:${String(n%60).padStart(2,'0')}`;
      for (let i=0;i<Number(f.get('count'));i++) {
        const end = start+Number(f.get('minutes'));
        if (end>=1440) { dialog.querySelector('[role=alert]').textContent='Все окна должны закончиться до полуночи.'; return; }
        added.push({id:crypto.randomUUID(),date:f.get('date'),start:time(start),end:time(end)}); start=end+Number(f.get('gap'));
      }
      data.windows = [...windows(data),...added]; dialog.close(); done();
    };
    dialog.addEventListener('close',()=>dialog.remove()); document.body.append(dialog); dialog.showModal();
  }
  function mount(ctx, blocks) {
    document.querySelectorAll('[data-defense-demo]').forEach(root => {
      const sourceId = root.dataset.defenseSource, data = JSON.parse(root.dataset.defenseData), blockId = data.block_id;
      const admin = !ctx.studentView && (new URLSearchParams(location.search).has('preview') || ctx.defenseAdmin) && ctx.canEditGroup(sourceId);
      const viewer = ctx.viewedStudent?.id || 'student-0';
      const getBookings = () => read()[sourceId]?.[blockId] || [];
      const candidates = () => ctx.studentAccounts.rows().filter(p => p.groups.some(id => {
        const g = ctx.groups[id]; if (!g || ctx.closes(g) < new Date().toISOString().slice(0,10)) return false;
        return id === sourceId || (g.page_blocks || []).some(b => b.type === 'alias' && b.data.visible !== false && b.data.source_group === sourceId && b.data.source_block === blockId);
      }));
      const load = async () => {
        const booked = getBookings(), busy = booked.flatMap(b => b.participants.map(p => p.id));
        const now = new Intl.DateTimeFormat('sv-SE',{timeZone:'Asia/Yekaterinburg',year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit',hour12:false}).format(new Date());
        return {open:data.open !== false,text:data.text || '',admin,viewer,readOnly:ctx.studentView || (!admin && !candidates().some(p => p.id === viewer)),
          candidates:candidates().map(p => ({id:p.id,name:p.name,busy:busy.includes(p.id)})),
          slots:windows(data).sort((a,b)=>(a.date+a.start).localeCompare(b.date+b.start)).map(slot => {
            const booking = booked.find(b => b.slot_id === slot.id), past = slot.date+' '+slot.start <= now;
            return {...slot,past,booking:booking ? {...booking,mine:booking.participants.some(p => p.id === viewer),can_manage:!ctx.studentView && (admin || booking.creator === viewer && !past)} : null};
          })};
      };
      const change = async input => {
        const mutate = async () => {
          if (ctx.studentView) throw Error('В режиме просмотра запись недоступна.');
          const all = read(); all[sourceId] ||= {}; const booked = all[sourceId][blockId] ||= [];
          const state = await load(), booking = booked.find(b => String(b.id) === String(input.booking_id));
          if (input.action !== 'book') {
            if (!booking || booking.revision !== Number(input.revision)) throw Error('Запись уже изменена. Обновите расписание.');
            if (!admin && booking.creator !== viewer) throw Error('Запись может изменить её автор или администратор.');
          }
          if (input.action === 'cancel') booked.splice(booked.indexOf(booking),1);
          else {
            const slot = state.slots.find(s => s.id === input.slot_id);
            if (!state.open || !slot || slot.past) throw Error('Выберите доступное будущее окно.');
            if (slot.booking && slot.booking.id !== booking?.id) throw Error('Это окно уже занято. Выберите другое время.');
            if (input.action === 'move') Object.assign(booking,{slot_id:slot.id,date:slot.date,start:slot.start,end:slot.end,revision:booking.revision+1});
            else {
              const ids = [admin ? input.student_id : viewer,...(input.partner_id ? [input.partner_id] : [])];
              if (new Set(ids).size !== ids.length || ids.some(id => !state.candidates.some(p => p.id === id && !p.busy))) throw Error('Один из участников уже записан или недоступен. Выберите слушателей из списка.');
              booked.push({id:crypto.randomUUID(),revision:1,slot_id:slot.id,date:slot.date,start:slot.start,end:slot.end,creator:viewer,topic:input.topic || '',participants:ids.map(id => {const p = candidates().find(p => p.id === id);return {id:p.id,name:p.name};})});
            }
          }
          localStorage.setItem(key,JSON.stringify(all)); return load();
        };
        return navigator.locks ? navigator.locks.request(key,mutate) : mutate();
      };
      load().then(state => {root.dataset.defenseState=JSON.stringify(state); window.DefenseBooking.mount(root,{load,change});});
    });
  }
  return {windows,preview,prepare,generate,mount};
})();
