(function () {
  'use strict';
  var root = document.getElementById('lk-transition-options');
  if (!root) return;
  var question = root.querySelector('.lk-question');
  if (question) {
    question.querySelectorAll('[data-answer]').forEach(function (button) {
      button.addEventListener('click', function () {
        var answer = button.dataset.answer;
        question.querySelectorAll('[data-answer]').forEach(function (item) {
          item.setAttribute('aria-pressed', String(item.dataset.answer === answer));
        });
        question.querySelectorAll('[data-result]').forEach(function (panel) {
          panel.hidden = panel.dataset.result !== answer;
        });
      });
    });
  }
  root.querySelectorAll('[data-first-steps]').forEach(function (button) {
    button.addEventListener('click', function () {
      root.querySelector('[data-steps]').scrollIntoView({ behavior: 'auto', block: 'start' });
    });
  });
})();
