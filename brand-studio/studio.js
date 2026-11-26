'use strict';

const colors = [['#0E394B', 'Тёмная основа'], ['#226D8D', 'Синий'], ['#6BA6C0', 'Голубой'], ['#8D1638', 'Бордовый акцент'], ['#F2F7FB', 'Светлый фон']];
const $ = (selector) => document.querySelector(selector);
const escapeHTML = (value) => String(value).replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
let toastTimer;

function showToast(message) {
  clearTimeout(toastTimer);
  $('#toast').textContent = message;
  $('#toast').hidden = false;
  toastTimer = setTimeout(() => { $('#toast').hidden = true; }, 4500);
}
function route(moveFocus = false) {
  const page = location.hash.slice(1) || 'brand';
  if (page === 'main') { $('#main').focus(); return; }
  const validPage = ['brand', 'pilot', 'concept'].includes(page) ? page : 'brand';
  document.querySelectorAll('.page').forEach((element) => { element.hidden = element.id !== `page-${validPage}`; });
  document.querySelectorAll('[data-page]').forEach((link) => {
    if (link.dataset.page === validPage) link.setAttribute('aria-current', 'page');
    else link.removeAttribute('aria-current');
  });
  document.title = `${document.querySelector(`[data-page="${validPage}"]`).textContent.trim().replace(/^\d+\s*/, '')} — Студия ВЭШ`;
  if (moveFocus) { $('#main').focus({ preventScroll: true }); window.scrollTo(0, 0); }
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

$('#palette').innerHTML = colors.map(([hex, name]) => `<button type="button" class="swatch" data-color="${hex}" aria-label="Скопировать ${name}: ${hex}"><i style="background:${hex}" aria-hidden="true"></i><span>${hex}</span><small>${name}</small></button>`).join('');
document.addEventListener('click', async (event) => {
  const swatch = event.target.closest('[data-color]');
  if (!swatch) return;
  try { await navigator.clipboard.writeText(swatch.dataset.color); showToast(`Скопировано: ${swatch.dataset.color}`); }
  catch { showToast(`Не удалось скопировать. Код цвета: ${swatch.dataset.color}`); }
});
window.addEventListener('hashchange', () => route(true));
renderKit();
route();
