import {feedbackDashboard} from './feedback-summary.mjs?v=20261127-feedback-1';
import {active, getCourse} from './domain.mjs?v=20261126-student-access-1';
import {escapeHtml as h} from './course-view.mjs?v=20261126-public-safe-1';

export function submitCourseFeedback(state, enrollmentId, values) {
  const enrollment = state.enrollments.find(item => item.id === enrollmentId && item.userId === state.userId);
  if (!active(state, enrollment)) throw new Error('Для отправки отзыва нужен действующий доступ к курсу.');
  if (!/^[1-5]$/.test(String(values.rating ?? ''))) throw new Error('Выберите оценку курса от 1 до 5.');
  const liked = String(values.liked ?? '').trim();
  const improvements = String(values.improvements ?? '').trim();
  if (liked.length > 5000 || improvements.length > 5000) throw new Error('В каждом комментарии можно написать до 5000 символов.');
  enrollment.feedback = {rating: Number(values.rating), liked, improvements, submittedAt: state.now};
  return enrollment.feedback;
}

export function feedbackMenu({base, number, feedback, selected, recorded = false}) {
  return `<a class="aos-menu ${recorded ? 'aos-menu-module' : ''}" href="${base}/feedback" ${selected ? 'aria-current="page"' : ''}><span class="${recorded ? 'rc-module-number' : 'lesson-num'}">${String(number).padStart(2, '0')}</span><span>Анкета обратной связи<small data-feedback-state>${feedback ? 'Ответ отправлен' : 'Оцените курс'}</small></span><span aria-hidden="true">${feedback ? '✓' : '→'}</span></a>`;
}

export function feedbackForm({enrollment, preview = false, backUrl, homeUrl}) {
  const answer = enrollment.feedback;
  return `<section class="aos-panel" aria-labelledby="aos-title"><p class="eyebrow">Завершение курса</p><h2 id="aos-title">Анкета обратной связи</h2><p class="aos-intro">Поделитесь впечатлениями об обучении. Ваши ответы помогут нам сделать курс полезнее.</p>
    ${answer ? '<div class="aos-success" role="status"><strong>Спасибо за обратную связь!</strong><p>Ваш ответ сохранён. При желании его можно изменить.</p></div>' : ''}
    <form ${preview ? '' : 'id="feedback-form"'} data-enrollment="${h(enrollment.id)}">
      <fieldset class="aos-rating" ${preview ? 'disabled' : ''}><legend>Как вы оцениваете курс? <span aria-hidden="true">*</span></legend><div class="aos-rating-options">${[1, 2, 3, 4, 5].map(value => `<label><input type="radio" name="rating" value="${value}" required ${answer?.rating === value ? 'checked' : ''}><span>${value}</span></label>`).join('')}</div><div class="aos-scale"><span>1 — совсем не понравился</span><span>5 — очень понравился</span></div></fieldset>
      <label class="aos-field">Что понравилось?<textarea name="liked" rows="4" maxlength="5000" placeholder="Темы, примеры, подача материала — что было полезно для вас" ${preview ? 'disabled' : ''}>${h(answer?.liked)}</textarea></label>
      <label class="aos-field">Что не понравилось или можно улучшить?<textarea name="improvements" rows="4" maxlength="5000" placeholder="Чего не хватило, что было сложно или хотелось бы изменить" ${preview ? 'disabled' : ''}>${h(answer?.improvements)}</textarea></label>
      <p class="aos-hint">Комментарии по желанию. Ответ увидит команда курса.</p><p class="form-error" role="alert"></p>
      <button class="btn" type="submit" ${preview ? 'disabled' : ''}>${answer ? 'Сохранить изменения' : 'Отправить отзыв'}</button>${preview ? '<p class="aos-hint">Форма доступна для заполнения в режиме слушателя</p>' : ''}
    </form><div class="aos-navigation"><a href="${backUrl}">← К последнему уроку</a><a href="${homeUrl}">${preview ? 'К управлению курсами' : 'К моему обучению'} →</a></div></section>`;
}

export function feedbackResponses(state, paid) { return feedbackDashboard(state, paid); }
