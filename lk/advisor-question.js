(() => {
  'use strict';
  document.addEventListener('click', event => {
    if (event.target.closest('[data-advisor-question-open]')) {
      const dialog = document.getElementById('advisor-question-dialog');
      if (dialog) { dialog.showModal(); dialog.querySelector('textarea')?.focus(); }
    }
    const close = event.target.closest('[data-advisor-question-close]');
    if (close) close.closest('dialog').close();
  });
  document.addEventListener('submit', event => {
    const form = event.target.closest('[data-advisor-question-form]');
    if (!form) return;
    const field = form.querySelector('textarea'), button = form.querySelector('[type="submit"]');
    field.setCustomValidity(field.value.trim() ? '' : 'Напишите ваш вопрос');
    if (!form.reportValidity()) { event.preventDefault(); return; }
    if (form.dataset.sending || form.hasAttribute('data-readonly')) { event.preventDefault(); return; }
    if (form.dataset.demo === 'true') {
      event.preventDefault();
      try {
        const key = 'vesh-advisor-question-demo-v1';
        const rows = JSON.parse(localStorage.getItem(key) || '[]');
        rows.push({question:field.value.trim(),advisor:form.dataset.advisor,group:form.dataset.group,created_at:new Date().toISOString()});
        localStorage.setItem(key, JSON.stringify(rows));
        form.closest('dialog').close(); field.value = '';
        document.querySelector('[data-advisor-question-status]').textContent = '✓ Вопрос отправлен';
      } catch { form.querySelector('[data-question-error]').textContent = 'Не удалось сохранить вопрос в браузере. Текст остаётся в поле'; }
      return;
    }
    form.dataset.sending = 'true'; button.disabled = true; button.textContent = 'Отправляем…';
  });
  document.addEventListener('input', event => { if (event.target.matches('[data-advisor-question-form] textarea')) event.target.setCustomValidity(''); });
  window.addEventListener('pageshow', () => document.querySelectorAll('[data-advisor-question-form]').forEach(form => {
    delete form.dataset.sending;
    const button = form.querySelector('[type="submit"]');
    if (button && !form.hasAttribute('data-readonly')) { button.disabled = false; button.textContent = 'Отправить вопрос'; }
  }));
  document.querySelector('[data-advisor-question-reopen]')?.showModal();
})();
