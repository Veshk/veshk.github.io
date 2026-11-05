'use strict';
window.AdminGroups = (() => {
  const tones = {'Запускается':'blue','Идет обучение':'green','На закрытии':'amber','Архив':'gray','обучение отложено':'rose','Продление доступа':'purple','Экскурсия':'cyan','Тех. группа':'gray'};
  const init = ctx => {
    const panel = document.getElementById('groups-panel');
    const host = document.getElementById('groups-list');
    const esc = ctx.escape;
    const collapsed = new Set();
    let sort = 'name', direction = 1;
    const value = id => document.getElementById(id).value;
    const badge = status => `<span class="group-status status-${tones[status] || 'gray'}"><span aria-hidden="true"></span>${esc(status || 'Статус не указан')}</span>`;
    document.getElementById('group-status-filter').innerHTML = '<option value="">Все статусы</option>' + ctx.statuses.map(s => `<option>${esc(s)}</option>`).join('');
    document.getElementById('group-owner-filter').innerHTML = '<option value="">Все администраторы</option>' + Object.entries(ctx.names).map(([id,name]) => `<option value="${id}">${esc(name)}</option>`).join('');
    const resetFilters = () => {
      ['group-search','group-program-filter','group-status-filter','group-owner-filter'].forEach(id => { document.getElementById(id).value = ''; });
      document.getElementById('group-mine').checked = false;
      collapsed.clear();
    };
    const render = () => {
      const previousScroll = host.querySelector('.group-grid-scroll');
      const scrollLeft = previousScroll?.scrollLeft || 0, scrollTop = previousScroll?.scrollTop || 0;
      const focused = host.contains(document.activeElement) ? document.activeElement : null;
      const focusAttribute = ['data-group-sort','data-group-collapse'].find(name => focused?.hasAttribute(name));
      const focusValue = focusAttribute ? focused.getAttribute(focusAttribute) : null;
      const search = value('group-search').trim().toLocaleLowerCase('ru');
      const program = value('group-program-filter'), status = value('group-status-filter'), owner = value('group-owner-filter');
      const mine = document.getElementById('group-mine').checked;
      const grouping = value('group-by');
      const details = document.getElementById('group-more-columns').checked;
      const all = Object.entries(ctx.groups);
      const rows = all.filter(([,g]) => (!program || g.program_id === program) && (!status || g.status === status) && (!owner || g.id === owner) && (!mine || g.id === ctx.role) && [g.name,g.code,g.number,g.owner,ctx.programs[g.program_id]?.title,ctx.programs[g.program_id]?.tag].join(' ').toLocaleLowerCase('ru').includes(search));
      rows.sort((a,b) => String(a[1][sort] || '').localeCompare(String(b[1][sort] || ''), 'ru', {numeric:true}) * direction || a[0].localeCompare(b[0]));
      document.getElementById('groups-count').textContent = `Показано ${rows.length} из ${all.length}`;
      document.getElementById('group-reset-filters').hidden = !Boolean(search || program || status || owner || mine);
      const buckets = new Map();
      for (const row of rows) {
        const key = grouping === 'owner' ? row[1].id : grouping === 'status' ? row[1].status || '' : '';
        if (!buckets.has(key)) buckets.set(key, []);
        buckets.get(key).push(row);
      }
      const headers = [['name','Название группы'],['status','Статус'],['code','ТЕГ группы'],['number','№'],['owner','Администратор'],[null,'Чат'],[null,'Действия']];
      if (details) headers.push(['starts_at','Запуск'],['graduates_at','Выпуск'],[null,'Доступ до'],['format','Формат'],[null,'Приём заявок']);
      const header = headers.map(([key,label]) => `<th scope="col"${key === sort ? ` aria-sort="${direction === 1 ? 'ascending' : 'descending'}"` : ''}>${key ? `<button data-group-sort="${key}">${label}<span aria-hidden="true">${key === sort ? direction === 1 ? '↑' : '↓' : '↕'}</span></button>` : label}</th>`).join('');
      const rowHtml = ([id,g]) => {
        const editable = ctx.canEditGroup(id), chat = ctx.safeUrl(g.chat_url);
        return `<tr class="group-data-row" data-group-row="${esc(id)}"><td><button class="group-name" data-group-view="${esc(id)}">${esc(g.name)}</button><span class="group-subtitle">${esc(ctx.programs[g.program_id]?.section || 'Раздел не указан')}</span></td><td>${badge(g.status)}</td><td class="group-code">${esc(g.code)}</td><td>${esc(g.number || '—')}</td><td><span class="group-owner">${esc(g.owner)}</span>${!editable ? '<small class="group-readonly">Только просмотр</small>' : ''}</td><td>${chat ? `<a class="group-chat" href="${esc(chat)}" target="_blank" rel="noopener" aria-label="Чат группы ${esc(g.code)}">Чат ↗</a>` : '<span class="group-unset">—</span>'}</td><td><div class="group-row-actions"><a class="group-action" href="${ctx.groupLink(id)}&preview=1&role=${ctx.role}" target="_blank" rel="noopener">Зайти в ЛК ↗</a>${editable ? `<a class="group-action group-action-primary" href="editor.html?group=${encodeURIComponent(id)}&role=${ctx.role}">Редактор</a><button class="group-settings" data-group-edit="${esc(id)}" aria-label="Настройки группы ${esc(g.code)}" title="Настройки группы"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 7h16M4 17h16M9 4v6M15 14v6"/></svg></button>` : ''}</div></td>${details ? `<td>${ctx.dateLabel(g.starts_at)}</td><td>${ctx.dateLabel(g.graduates_at)}</td><td>${ctx.dateLabel(ctx.closes(g))}</td><td>${esc(g.format || '—')}</td><td>${g.registration_open === false ? 'Закрыт' : 'Открыт'}</td>` : ''}</tr>`;
      };
      const body = [...buckets.entries()].sort(([a],[b]) => a.localeCompare(b,'ru')).map(([key,items]) => {
        const groupKey = grouping + ':' + key;
        const label = grouping === 'owner' ? ctx.names[key] || items[0][1].owner || 'Не назначен' : key || 'Статус не указан';
        const isCollapsed = collapsed.has(groupKey);
        return (grouping ? `<tr class="group-divider"><th colspan="${headers.length}" scope="rowgroup"><button data-group-collapse="${esc(groupKey)}" aria-expanded="${!isCollapsed}"><span class="group-chevron" aria-hidden="true">${isCollapsed ? '▸' : '▾'}</span><span class="group-divider-label">${grouping === 'owner' ? 'Администратор' : 'Статус'}</span>${grouping === 'status' ? badge(label) : `<strong>${esc(label)}</strong>`}<span class="group-bucket-count">${items.length}</span></button></th></tr>` : '') + (isCollapsed && grouping ? '' : items.map(rowHtml).join(''));
      }).join('');
      host.innerHTML = `<div class="group-grid-scroll" tabindex="0" role="region" aria-label="Таблица учебных групп"><table class="group-grid"><caption class="sr-only">Учебные группы. Нажмите на название, чтобы открыть карточку группы.</caption><thead><tr>${header}</tr></thead><tbody>${body || `<tr><td colspan="${headers.length}" class="group-empty"><strong>Группы не найдены</strong><p>Измените поиск или сбросьте фильтры.</p><button class="group-action" data-group-reset>Сбросить фильтры</button></td></tr>`}</tbody></table></div>`;
      const scroll = host.querySelector('.group-grid-scroll');
      scroll.scrollLeft = scrollLeft; scroll.scrollTop = scrollTop;
      if (focusAttribute) host.querySelector(`[${focusAttribute}="${CSS.escape(focusValue)}"]`)?.focus({preventScroll:true});
    };
    host.addEventListener('click', event => {
      const button = event.target.closest('button');
      if (!button) return;
      if (button.hasAttribute('data-group-sort')) {
        const next = button.dataset.groupSort;
        direction = sort === next ? -direction : 1; sort = next; render();
      } else if (button.hasAttribute('data-group-collapse')) {
        const key = button.dataset.groupCollapse;
        if (collapsed.has(key)) collapsed.delete(key); else collapsed.add(key);
        render();
      } else if (button.hasAttribute('data-group-view')) ctx.open('group-view',button.dataset.groupView);
      else if (button.hasAttribute('data-group-edit')) ctx.open('group-edit',button.dataset.groupEdit);
      else if (button.hasAttribute('data-group-reset')) { resetFilters(); render(); }
    });
    ['group-status-filter','group-owner-filter','group-mine','group-by','group-more-columns'].forEach(id => document.getElementById(id).addEventListener('change', () => { collapsed.clear(); render(); }));
    panel.querySelector('#group-reset-filters').addEventListener('click', () => { resetFilters(); render(); });
    return {render,resetFilters};
  };
  return {init};
})();
