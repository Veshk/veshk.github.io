'use strict';

// Локальная демонстрация. Рабочие сервисы и серверные данные не подключены.
const templates = [
  { id: 'presentation', category: 'slides', name: 'Презентация', tool: 'PowerPoint', kind: '', title: 'Идеи, которые\nстановятся делом', description: 'Обложка, содержание, ключевые тезисы и завершение.', tag: 'Для выступления' },
  { id: 'guide', category: 'documents', name: 'Методичка или раздатка', tool: 'Word / PDF', kind: 'document', title: 'От знаний\nк практике', description: 'Материал для участников с заданиями и выводами.', tag: 'Для обучения' },
  { id: 'announcement', category: 'digital', name: 'Комплект мастер-класса', tool: 'Сайт / соцсети / обложки', kind: 'announcement', title: 'Встречаемся\nв ВЭШ', description: 'Первый пилот: общие данные, состояния и плашки.', tag: 'Для приглашения' },
  { id: 'email', category: 'digital', name: 'Письмо участникам', tool: 'enKod', kind: 'email', title: 'До встречи\nв школе', description: 'Структура письма, основной текст и действие.', tag: 'Для рассылки' },
  { id: 'program', category: 'documents', name: 'Программа обучения', tool: 'Word / PDF', kind: 'program', title: 'Следующий\nшаг в развитии', description: 'Описание программы, модули и условия участия.', tag: 'Для знакомства' },
  { id: 'social', category: 'digital', name: 'Карточка для соцсетей', tool: 'Изображение', kind: 'social', title: 'Есть о чём\nпоговорить', description: 'Короткий тезис, цитата или приглашение к диалогу.', tag: 'Для публикации' }
];
const colors = [['#0E394B', 'Тёмная основа'], ['#226D8D', 'Синий'], ['#6BA6C0', 'Голубой'], ['#8D1638', 'Бордовый акцент'], ['#F2F7FB', 'Светлый фон']];
const storageKey = 'vesh-brand-studio-public-drafts-v1';
const $ = (selector) => document.querySelector(selector);
const escapeHTML = (value) => String(value).replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
let activeFilter = 'all';
let toastTimer;
let openedFrom;

function showToast(message) {
  clearTimeout(toastTimer);
  $('#toast').textContent = message;
  $('#toast').hidden = false;
  toastTimer = setTimeout(() => { $('#toast').hidden = true; }, 4500);
}
function renderCard(template) {
  return `<article class="template-card"><button class="template-link" type="button" data-template="${template.id}" aria-label="Попробовать эскиз: ${template.name}"><div class="template-visual"><div class="mini-slide ${template.kind}" aria-hidden="true"><small>ВЫСШАЯ ЭКОНОМИЧЕСКАЯ ШКОЛА</small><strong>${template.title.replace('\n', '<br>')}</strong><img src="assets/motif.svg" alt=""></div></div><div class="template-meta"><span>${template.tool}</span><span>Эскиз</span></div><h3>${template.name}</h3><p>${template.description}</p><span class="card-action">Попробовать эскиз →</span></button></article>`;
}
function renderCatalog() {
  const query = $('#template-search').value.trim().toLocaleLowerCase('ru');
  const found = templates.filter((template) => (activeFilter === 'all' || template.category === activeFilter) && `${template.name} ${template.tool} ${template.description}`.toLocaleLowerCase('ru').includes(query));
  $('#catalog').innerHTML = found.map(renderCard).join('');
  $('#catalog-empty').hidden = found.length > 0;
  $('#catalog-count').textContent = `Найдено: ${found.length}`;
}
function route(moveFocus = false) {
  const page = location.hash.slice(1) || 'home';
  if (page === 'main') { $('#main').focus(); return; }
  const validPage = ['home', 'templates', 'brand', 'pilot', 'drafts', 'concept'].includes(page) ? page : 'home';
  document.querySelectorAll('.page').forEach((element) => { element.hidden = element.id !== `page-${validPage}`; });
  document.querySelectorAll('[data-page]').forEach((link) => {
    if (link.dataset.page === validPage) link.setAttribute('aria-current', 'page');
    else link.removeAttribute('aria-current');
  });
  if (validPage === 'drafts') renderDrafts();
  document.title = `${document.querySelector(`[data-page="${validPage}"]`).textContent.trim().replace(/^\d+\s*/, '')} — Студия ВЭШ`;
  if (moveFocus) { $('#main').focus({ preventScroll: true }); window.scrollTo(0, 0); }
}
function readDrafts() {
  const raw = localStorage.getItem(storageKey);
  if (!raw) return [];
  const items = JSON.parse(raw);
  if (!Array.isArray(items) || items.some((item) => !item || typeof item.id !== 'string' || !templates.some((template) => template.id === item.template) || typeof item.title !== 'string' || typeof item.updatedAt !== 'string' || !Number.isFinite(Date.parse(item.updatedAt)) || ['subtitle', 'detail', 'notes'].some((key) => typeof item[key] !== 'string') || !['light', 'dark'].includes(item.tone))) throw new Error('Invalid draft data');
  return items;
}
function draftFromForm() {
  return { id: $('#draft-id').value || (globalThis.crypto?.randomUUID ? crypto.randomUUID() : `draft-${Date.now()}-${Math.random().toString(16).slice(2)}`), template: $('#draft-template').value, title: $('#material-title').value.trim(), subtitle: $('#material-subtitle').value.trim(), detail: $('#material-detail').value.trim(), notes: $('#material-notes').value.trim(), tone: document.querySelector('[name="tone"]:checked').value, updatedAt: new Date().toISOString() };
}
function validateForm() {
  $('#material-title').setCustomValidity($('#material-title').value.trim() ? '' : 'Введите название материала.');
  return $('#editor-form').reportValidity();
}
function updatePreview() {
  $('#material-title').setCustomValidity('');
  $('#preview-title').textContent = $('#material-title').value.trim() || 'Название вашего материала';
  $('#preview-subtitle').textContent = $('#material-subtitle').value.trim() || 'Здесь появится ваша главная мысль';
  $('#preview-detail').textContent = $('#material-detail').value.trim();
  $('#live-preview').className = `live-preview ${document.querySelector('[name="tone"]:checked').value}`;
  $('#editor-feedback').textContent = 'Изменения ещё не сохранены.';
}
function openEditor(templateId, draft) {
  const template = templates.find((item) => item.id === templateId);
  if (!template) return;
  openedFrom = document.activeElement;
  $('#editor-form').reset();
  $('#draft-id').value = draft?.id || '';
  $('#draft-template').value = templateId;
  $('#editor-title').textContent = template.name;
  $('#preview-format').textContent = template.tag;
  $('#material-title').value = draft?.title || '';
  $('#material-subtitle').value = draft?.subtitle || '';
  $('#material-detail').value = draft?.detail || '';
  $('#material-notes').value = draft?.notes || '';
  document.querySelector(`[name="tone"][value="${draft?.tone === 'light' ? 'light' : 'dark'}"]`).checked = true;
  updatePreview();
  $('#editor-feedback').textContent = draft ? 'Открыт сохранённый черновик.' : '';
  $('#editor').showModal();
  $('#material-title').focus();
}
function saveDraft(event) {
  event.preventDefault();
  if (!validateForm()) return;
  const draft = draftFromForm();
  try {
    const drafts = readDrafts();
    const index = drafts.findIndex((item) => item.id === draft.id);
    if (index >= 0) drafts[index] = draft; else drafts.unshift(draft);
    localStorage.setItem(storageKey, JSON.stringify(drafts));
    $('#draft-id').value = draft.id;
    $('#editor-feedback').textContent = 'Черновик сохранён в этом браузере. Он доступен в «Моих черновиках».';
    renderDrafts();
  } catch {
    $('#editor-feedback').textContent = 'Сохранить не удалось: хранилище недоступно, заполнено или содержит повреждённые данные. Скачайте задание JSON, чтобы не потерять работу.';
  }
}
function renderDrafts() {
  let drafts;
  try { drafts = readDrafts(); } catch { $('#draft-list').innerHTML = '<p class="notice">Черновики не удалось прочитать. Сохранённые данные не изменены. Можно продолжить работу с шаблоном и скачать задание JSON.</p>'; $('#draft-empty').hidden = true; return; }
  $('#draft-empty').hidden = drafts.length > 0;
  $('#draft-list').innerHTML = drafts.map((draft) => {
    const template = templates.find((item) => item.id === draft.template);
    const updated = new Intl.DateTimeFormat('ru-RU', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Asia/Yekaterinburg' }).format(new Date(draft.updatedAt));
    return `<article class="draft-row"><div><h3>${escapeHTML(draft.title)}</h3><p>${template.name} / Изменён ${updated} ЕКБ</p></div><div class="draft-actions"><button type="button" data-open-draft="${escapeHTML(draft.id)}">Открыть</button><button type="button" data-delete-draft="${escapeHTML(draft.id)}" class="delete-draft">Удалить</button></div></article>`;
  }).join('');
}

function kitData() {
  return { schemaVersion: 1, status: 'concept-awaiting-source-layouts', title: $('#kit-title').value.trim(), dateText: $('#kit-date').value.trim(), speaker: $('#kit-speaker').value.trim(), badge: $('#kit-badge').value.trim(), registration: document.querySelector('[name="kit-state"]:checked').value, photo: null, sourceLayout: null, outputSizes: null, createdAt: new Date().toISOString() };
}
function renderKit() {
  const kit = kitData();
  $('#kit-title').setCustomValidity('');
  $('#kit-feedback').textContent = '';
  const title = escapeHTML(kit.title || 'Тема вашего мастер-класса');
  const speaker = escapeHTML(kit.speaker || 'Имя спикера');
  const date = escapeHTML(kit.dateText || 'Дата и время');
  const badge = kit.badge ? `<span class="kit-badge">${escapeHTML(kit.badge)}</span>` : '';
  $('#kit-previews').innerHTML = [['wide', 'Обложка'], ['square', 'Соцсети'], ['portrait', 'Вертикальная карточка']].map(([kind, label]) => `<article class="kit-output ${kind}"><span class="small-label">${label}</span><div class="kit-canvas ${kind} ${kit.registration}"><span class="kit-school">ВЫСШАЯ ЭКОНОМИЧЕСКАЯ ШКОЛА</span>${badge}<h3>${title}</h3><p>${date}</p><div class="kit-person"><span class="photo-placeholder" aria-hidden="true">Фото</span><span>${speaker}</span></div><strong class="kit-registration">${kit.registration === 'open' ? 'Регистрация открыта' : 'Регистрация закрыта'}</strong></div></article>`).join('');
}
$('#kit-form').addEventListener('input', renderKit);
$('#kit-form').addEventListener('submit', (event) => {
  event.preventDefault();
  const kit = kitData();
  $('#kit-title').setCustomValidity(kit.title ? '' : 'Введите тему мастер-класса.');
  if (!$('#kit-form').reportValidity()) return;
  const url = URL.createObjectURL(new Blob([JSON.stringify(kit, null, 2)], { type: 'application/json;charset=utf-8' }));
  const link = document.createElement('a');
  link.href = url; link.download = 'vesh-mk-kit-brief.json';
  document.body.appendChild(link); link.click(); link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
  $('#kit-feedback').textContent = 'Задание на комплект подготовлено к скачиванию. Исходный макет, фото и размеры ещё не заданы.';
});

$('#home-templates').innerHTML = [templates[2], templates[0], templates[1]].map(renderCard).join('');
$('#palette').innerHTML = colors.map(([hex, name]) => `<button type="button" class="swatch" data-color="${hex}" aria-label="Скопировать ${name}: ${hex}"><i style="background:${hex}" aria-hidden="true"></i><span>${hex}</span><small>${name}</small></button>`).join('');
$('#template-search').addEventListener('input', renderCatalog);
$('#editor-form').addEventListener('input', updatePreview);
$('#editor-form').addEventListener('submit', saveDraft);
$('#close-editor').addEventListener('click', () => $('#editor').close());
$('#editor').addEventListener('close', () => { if (openedFrom?.isConnected) openedFrom.focus(); });
$('#download-brief').addEventListener('click', () => {
  if (!validateForm()) return;
  const draft = draftFromForm();
  const blob = new Blob([JSON.stringify({ schemaVersion: 1, status: 'concept', ...draft }, null, 2)], { type: 'application/json;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `vesh-brief-${draft.template}.json`;
  document.body.appendChild(link); link.click(); link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
  $('#editor-feedback').textContent = 'Задание JSON подготовлено к скачиванию. Это данные для материала, не готовая презентация.';
});
document.addEventListener('click', async (event) => {
  const templateButton = event.target.closest('[data-template]');
  if (templateButton) {
    if (templateButton.dataset.template === 'announcement') location.hash = 'pilot';
    else openEditor(templateButton.dataset.template);
  }
  const filter = event.target.closest('[data-filter]');
  if (filter) { activeFilter = filter.dataset.filter; document.querySelectorAll('[data-filter]').forEach((button) => button.setAttribute('aria-pressed', String(button === filter))); renderCatalog(); }
  const swatch = event.target.closest('[data-color]');
  if (swatch) {
    try { await navigator.clipboard.writeText(swatch.dataset.color); showToast(`Скопировано: ${swatch.dataset.color}`); }
    catch { showToast(`Не удалось скопировать. Код цвета: ${swatch.dataset.color}`); }
  }
  const open = event.target.closest('[data-open-draft]');
  const remove = event.target.closest('[data-delete-draft]');
  if (open || remove) {
    try {
      const drafts = readDrafts();
      if (open) { const draft = drafts.find((item) => item.id === open.dataset.openDraft); if (draft) openEditor(draft.template, draft); }
      if (remove) {
        const id = remove.dataset.deleteDraft;
        const draft = drafts.find((item) => item.id === id);
        if (draft && confirm(`Удалить локальный черновик «${draft.title}»?`)) { localStorage.setItem(storageKey, JSON.stringify(drafts.filter((item) => item.id !== id))); renderDrafts(); showToast('Черновик удалён из этого браузера.'); }
      }
    } catch { showToast('Хранилище недоступно. Черновики не изменены.'); }
  }
});
window.addEventListener('hashchange', () => route(true));
window.addEventListener('storage', (event) => { if (event.key === storageKey) renderDrafts(); });
renderCatalog();
renderKit();
route();
