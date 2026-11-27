(() => {
  'use strict';
  const dialog = document.getElementById('support-dialog');
  let opener;
  const open = (event) => {
    if (!dialog || !dialog.showModal) return;
    event.preventDefault();
    opener = event.currentTarget;
    const title = dialog.querySelector('[data-support-origin-title]');
    if (title && !title.value) title.value = document.title.slice(0, 200);
    dialog.showModal();
    dialog.querySelector('[name="subject"]')?.focus();
  };
  document.querySelectorAll('[data-support-open]').forEach((button) => button.addEventListener('click', open));
  dialog?.querySelector('[data-support-close]')?.addEventListener('click', () => dialog.close());
  dialog?.addEventListener('close', () => opener?.focus());
  document.querySelectorAll('[data-support-form]').forEach((form) => {
    const input = form.querySelector('[data-support-files]');
    const status = form.querySelector('[data-support-file-status]');
    const checkFiles = () => {
      const files = [...(input?.files || [])];
      let error = '';
      if (files.length > 3) error = 'Можно приложить до трёх файлов.';
      else if (files.some((file) => file.size > 5 * 1024 * 1024)) error = 'Каждый файл должен быть не больше 5 МБ.';
      else if (files.some((file) => !/\.(jpe?g|png|webp|pdf|docx?|xlsx?|txt)$/i.test(file.name))) error = 'Подойдут JPG, PNG, WebP, PDF, Word, Excel или TXT.';
      input?.setCustomValidity(error);
      if (status) status.textContent = error;
      return !error;
    };
    input?.addEventListener('change', checkFiles);
    form.addEventListener('submit', (event) => {
      if (!checkFiles()) { event.preventDefault(); input?.reportValidity(); return; }
      const submit = form.querySelector('button[type="submit"], .support-primary');
      if (submit) { submit.disabled = true; submit.textContent = 'Отправляем…'; }
    });
  });
  window.addEventListener('pageshow', () => {
    document.querySelectorAll('[data-support-form] .support-primary').forEach((button) => {
      if (button.disabled) { button.disabled = false; button.textContent = button.form.action.endsWith('/reply') ? 'Отправить сообщение' : 'Отправить обращение'; }
    });
  });
})();
