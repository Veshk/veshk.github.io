'use strict';
(() => {
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[c]));
  const dateLabel = date => new Intl.DateTimeFormat('ru', {day:'numeric', month:'long', weekday:'short', timeZone:'UTC'}).format(new Date(date+'T12:00:00Z'));
  const names = booking => booking.participants.map(p => p.name).join(' и ');
  const stamp = slot => `${dateLabel(slot.date)}, ${slot.start}–${slot.end}`;
  function mount(root, adapter) {
    if (root.dataset.defenseMounted) return;
    root.dataset.defenseMounted = '1';
    let state = JSON.parse(root.dataset.defenseState), selectedDate = '', pending = false, message = '', selected = null, mode = '', moving = null;
    const endpoint = root.dataset.defenseEndpoint;
    const load = adapter ? adapter.load : async () => {
      const response = await fetch(endpoint, {headers:{Accept:'application/json'}, cache:'no-store'});
      if (!response.ok) throw new Error('Не удалось обновить расписание. Обновите страницу.');
      return response.json();
    };
    const change = adapter ? adapter.change : async body => {
      const response = await fetch(endpoint, {method:'POST', headers:{Accept:'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN':root.dataset.defenseCsrf}, body:JSON.stringify(body)});
      const result = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(response.status === 419 ? 'Сессия закончилась. Обновите страницу.' : Object.values(result.errors || {}).flat()[0] || result.message || 'Не удалось сохранить запись. Попробуйте ещё раз.');
      return result;
    };
    const mine = () => state.slots.find(s => s.booking?.mine);
    function render() {
      const dates = [...new Set(state.slots.map(s => s.date))];
      if (!dates.includes(selectedDate)) selectedDate = mine()?.date || state.slots.find(s => !s.past)?.date || dates[0];
      const own = mine();
      root.innerHTML = `${state.text ? `<p class="block-text">${esc(state.text)}</p>` : ''}
        <div class="defense-heading"><span>Одно окно — одна курсовая · 1–2 участника</span><span>Время Екатеринбурга (UTC+5)</span></div>
        ${state.readOnly ? '<p class="defense-note">Предпросмотр · запись и изменения недоступны.</p>' : ''}
        ${!state.open ? '<p class="defense-note">Запись закрыта. Существующие записи сохранены.</p>' : ''}
        <div class="defense-message" role="status" aria-live="polite">${esc(message)}</div>
        ${own ? `<div class="defense-own"><div><span class="defense-caption">Ваша защита${own.booking.participants.length === 2 ? ' · вдвоём' : ''}</span><strong>${esc(stamp(own))}</strong><span>${esc(names(own.booking))}</span>${!own.booking.can_manage && !state.readOnly && !own.past ? '<small>Перенести или отменить общую запись может её автор или администратор.</small>' : ''}</div>${manage(own)}</div>` : ''}
        ${moving ? `<div class="defense-moving">Выберите новое свободное окно для ${esc(names(moving.booking))}. Прежнее время сохранится до подтверждения. <button type="button" data-stop-move>Отменить перенос</button></div>` : ''}
        ${dates.length ? `<div class="defense-dates" aria-label="Даты защиты">${dates.map(date => { const free = state.slots.filter(s => s.date === date && !s.booking && !s.past).length; return `<button type="button" data-date="${esc(date)}" aria-pressed="${date === selectedDate}"><strong>${esc(dateLabel(date))}</strong><span>${free ? `Свободно окон: ${free}` : 'Свободных окон нет'}</span></button>`; }).join('')}</div>
        <div class="defense-slots">${state.slots.filter(s => s.date === selectedDate).map(s => {
          const b = s.booking, available = !s.past && !b && state.open && !state.readOnly && (!own || moving || state.admin);
          return `<article class="defense-slot ${b?.mine ? 'is-mine' : ''} ${s.past ? 'is-past' : ''}"><div class="defense-time"><strong>${esc(s.start)}–${esc(s.end)}</strong><small>${duration(s)} мин</small></div><div class="defense-people">${b ? `<strong>${esc(names(b))}</strong><span>${b.mine ? 'Ваша запись' : 'Занято'}${b.participants.length === 2 ? ' · совместная защита' : ''}</span>${b.topic ? `<small>${esc(b.topic)}</small>` : ''}` : `<span>${s.past ? 'Окно прошло' : 'Свободно'}</span>`}</div><div class="defense-actions">${available ? `<button type="button" class="btn ${moving ? '' : 'outline'}" data-book="${esc(s.id)}">${moving ? 'Перенести сюда' : 'Записаться'}</button>` : ''}${b && !b.mine ? manage(s) : ''}</div></article>`;
        }).join('')}</div>` : '<div class="empty">Даты защиты пока не опубликованы.</div>'}
        ${state.admin ? '<p class="defense-note">Вы можете записать слушателя, перенести или отменить любую запись. Даты и окна настраиваются через «Редактировать» у этого блока.</p>' : ''}
        <dialog class="defense-dialog"><form><div class="defense-modal-head"><h3></h3><button type="button" data-close aria-label="Закрыть">×</button></div><div class="defense-modal-body"></div><p class="defense-modal-error" role="alert"></p><div class="defense-modal-actions"><button type="button" class="btn outline" data-close>Назад</button><button type="submit" class="btn">Подтвердить</button></div></form></dialog>`;
      root.querySelectorAll('[data-date]').forEach(button => button.onclick = () => { selectedDate = button.dataset.date; render(); root.querySelector(`[data-date="${selectedDate}"]`).focus(); });
      root.querySelectorAll('[data-book]').forEach(button => button.onclick = () => open(moving ? 'move' : 'book', state.slots.find(s => s.id === button.dataset.book)));
      root.querySelectorAll('[data-move]').forEach(button => button.onclick = () => { moving = state.slots.find(s => String(s.booking?.id) === button.dataset.move); message = ''; render(); root.querySelector('.defense-moving').scrollIntoView({block:'nearest', behavior:'smooth'}); });
      root.querySelectorAll('[data-cancel]').forEach(button => button.onclick = () => open('cancel', state.slots.find(s => String(s.booking?.id) === button.dataset.cancel)));
      root.querySelector('[data-stop-move]')?.addEventListener('click', () => { moving = null; render(); });
      root.querySelectorAll('[data-close]').forEach(button => button.onclick = () => { if (!pending) root.querySelector('dialog').close(); });
      root.querySelector('dialog').addEventListener('close', event => { if (!pending && event.target.isConnected) { render(); root.querySelector('[data-date]')?.focus(); } });
      root.querySelector('dialog').addEventListener('cancel', event => { if (pending) event.preventDefault(); });
      root.querySelector('dialog form').onsubmit = submit;
    }
    function duration(s) {
      const minutes = time => Number(time.slice(0,2))*60 + Number(time.slice(3,5));
      return minutes(s.end)-minutes(s.start);
    }
    function manage(s) {
      if (!s.booking?.can_manage) return '';
      return `<div class="defense-actions">${!s.past && state.open ? `<button type="button" class="defense-link" data-move="${s.booking.id}">Перенести</button>` : ''}<button type="button" class="defense-link" data-cancel="${s.booking.id}">Отменить запись</button></div>`;
    }
    function open(action, slot) {
      selected = slot; mode = action;
      const dialog = root.querySelector('dialog');
      dialog.querySelector('h3').textContent = action === 'cancel' ? 'Отменить запись на защиту?' : action === 'move' ? 'Перенести защиту?' : 'Запись на защиту';
      dialog.setAttribute('aria-label', dialog.querySelector('h3').textContent);
      const options = excluded => state.candidates.filter(p => !p.busy && String(p.id) !== String(excluded)).map(p => `<option value="${p.id}">${esc(p.name)}</option>`).join('');
      dialog.querySelector('.defense-modal-body').innerHTML = `<p class="defense-modal-time">${esc(stamp(slot))}</p>` + (action === 'book' ? `
        ${state.admin ? `<label class="field">Слушатель<select name="student_id" required><option value="">Выберите слушателя</option>${options(null)}</select></label>` : ''}
        <label class="field">Как будете защищать работу?<select name="format"><option value="solo">Один участник</option><option value="pair">Вдвоём</option></select></label>
        <label class="field" data-partner hidden>Второй участник<select name="partner_id"><option value="">Выберите напарника</option>${options(state.viewer)}</select></label>
        <p class="defense-note" data-pair-note hidden>Одна общая запись появится в ЛК обоих участников. Выберите напарника, с которым договорились защищаться вместе.</p>
        <label class="field">Тема курсовой (необязательно)<input name="topic" maxlength="300"></label>` : `<p>${esc(names((action === 'move' ? moving : slot).booking))}</p><p>${action === 'cancel' ? 'Запись отменится у всех её участников, а окно станет свободным.' : `Прежнее время: ${esc(stamp(moving))}. Запись перенесётся у всех участников.`}</p>`);
      dialog.querySelector('.defense-modal-error').textContent = '';
      dialog.querySelector('[type=submit]').textContent = action === 'cancel' ? 'Отменить запись' : action === 'move' ? 'Подтвердить перенос' : 'Подтвердить запись';
      dialog.querySelector('[name=format]')?.addEventListener('change', event => {
        const pair = event.target.value === 'pair';
        dialog.querySelector('[data-partner]').hidden = !pair;
        dialog.querySelector('[data-pair-note]').hidden = !pair;
        dialog.querySelector('[name=partner_id]').required = pair;
      });
      dialog.querySelector('[name=student_id]')?.addEventListener('change', event => {
        dialog.querySelector('[name=partner_id]').innerHTML = '<option value="">Выберите напарника</option>'+options(event.target.value);
      });
      dialog.showModal();
    }
    async function submit(event) {
      event.preventDefault();
      if (pending) return;
      const form = event.target, fields = new FormData(form), body = {action:mode};
      if (mode !== 'cancel') body.slot_id = selected.id;
      if (mode === 'book') {
        body.topic = fields.get('topic');
        if (state.admin) body.student_id = fields.get('student_id');
        if (fields.get('format') === 'pair') body.partner_id = fields.get('partner_id');
      } else {
        const booking = (mode === 'move' ? moving : selected).booking;
        body.booking_id = booking.id; body.revision = booking.revision;
      }
      pending = true;
      form.querySelectorAll('button').forEach(button => button.disabled = true);
      try {
        state = await change(body);
        message = mode === 'cancel' ? 'Запись отменена.' : mode === 'move' ? 'Запись перенесена у всех участников.' : 'Вы записаны на защиту. Запись доступна всем её участникам.';
        moving = null;
        form.closest('dialog').close();
        render();
        root.querySelector('[role=status]').setAttribute('tabindex','-1');
        root.querySelector('[role=status]').focus();
      } catch (error) {
        form.querySelector('.defense-modal-error').textContent = error.message;
        // Refresh behind the modal without discarding selections. The server rechecks on every attempt.
        try { state = await load(); } catch (_) { /* Keep the last schedule and explicit error. */ }
      } finally {
        pending = false;
        if (form.isConnected) form.querySelectorAll('button').forEach(button => button.disabled = false);
      }
    }
    async function refresh() {
      if (!root.isConnected || pending || root.querySelector('dialog')?.open || document.hidden) return;
      try {
        const fresh = await load();
        if (JSON.stringify(fresh) === JSON.stringify(state)) return;
        state = fresh;
        if (root.contains(document.activeElement)) return;
        if (moving) moving = state.slots.find(s => s.booking?.id === moving.booking.id) || null;
        render();
      } catch (_) { /* Booking remains subject to a fresh server check. */ }
    }
    render();
    if (endpoint || adapter) {
      const timer = setInterval(() => { if (!root.isConnected) clearInterval(timer); else refresh(); }, 15000);
      window.addEventListener('focus', refresh);
    }
  }
  window.DefenseBooking = {mount};
  const init = () => document.querySelectorAll('[data-defense-state]').forEach(root => mount(root));
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
