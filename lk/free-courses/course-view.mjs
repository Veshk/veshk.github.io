export const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const h = escapeHtml;
const external = (url, label, className='') => `<a class="${className}" href="${h(url)}" target="_blank" rel="noopener">${h(label)} ↗</a>`;

export function kinescopeEmbed(url) {
  const match = /^https:\/\/kinescope\.io\/([A-Za-z0-9]+)$/.exec(url || '');
  return match ? `https://kinescope.io/embed/${match[1]}?preload=false` : null;
}

export function lessonMedia(course, index) {
  if(course.kind==='paid-recorded')return `<div class="video-placeholder paid-placeholder"><div class="video-message"><span class="play-circle" aria-hidden="true">▷</span><strong>Видеоурок · ${course.minutes[index]} мин</strong><p>Здесь будет запись урока внутри ЛК.</p><small>В публичном прототипе показана структура курса. Платное видео доступно только в защищённом кабинете.</small></div></div>`;
  const lesson = course.content?.lessons[index];
  if (!lesson) return '<div class="video-placeholder"><div class="video-message"><span class="play-circle" aria-hidden="true">▷</span><strong>Место для видеоурока</strong><p>Материалы этого курса ещё не перенесены. Можно проверить навигацию и заметки.</p></div></div>';
  const embed = kinescopeEmbed(lesson.url);
  if (embed) return `<div class="lesson-player"><iframe data-kinescope-url="${h(lesson.url)}" data-embed-src="${h(embed)}" title="${h(lesson.title)} — видеоурок" allow="autoplay; fullscreen; picture-in-picture; encrypted-media" allowfullscreen></iframe><span class="player-loading" data-player-status role="status">Загружаем видео…</span></div><div class="media-caption"><span>${lesson.minutes} мин · Kinescope</span>${external(lesson.url,'Открыть видео отдельно')}</div>`;
  return `<div class="video-placeholder external-video"><div class="video-message"><span class="play-circle" aria-hidden="true">▷</span><strong>Видеоурок · ${lesson.minutes} мин</strong><p>Запись откроется в плеере Яндекс Диска.</p>${external(lesson.url,'Смотреть урок','btn')}</div></div>`;
}

export function courseMaterials(course) {
  const content = course.content;
  if(course.kind==='paid-recorded')return '<section class="card practice"><h3>Материалы и задание к уроку</h3><p class="muted">В рабочем кабинете здесь будут презентация, файлы и задание выбранного урока. Они хранятся в новой системе и выдаются после проверки доступа.</p></section>';
  if (!content) return `<div class="card practice"><h3>Попробуйте на практике</h3><p>${h(course.practice)}</p><p class="small muted">Пример задания для концепта. Без отправки на проверку.</p></div>`;
  if (!content.practice) return '';
  return `<section class="card practice" aria-labelledby="practice-title"><p class="eyebrow">Самостоятельная работа</p><h2 id="practice-title">Практическое задание</h2><p>${h(content.practice.intro)}</p><div class="resource-files">${content.materials.map(file=>external(file.url,file.name,'resource-file')).join('')}</div>${external(content.materialsFolder,'Открыть папку со всеми файлами','small')}<ol class="practice-steps">${content.practice.steps.map(step=>`<li><strong>${h(step.title)}</strong><p>${h(step.text)}</p></li>`).join('')}</ol></section>`;
}

export function courseAbout(course) {
  const content = course.content;
  if (!content) return '';
  return `<details class="card course-about"><summary>Об эксперте · ${h(content.expert)}</summary><div class="expert-profile"><img src="${h(content.expertPhoto)}" alt="${h(content.expert)}" loading="lazy"><div><h3>${h(content.expert)}</h3><p>${h(content.expertRole)}</p><ul>${content.expertBio.map(text=>`<li>${h(text)}</li>`).join('')}</ul>${content.expertVideo?external(content.expertVideo,'Видео об эксперте','small'):''}</div></div></details><section class="card course-help"><div><p class="eyebrow">Мы рядом</p><h3>Нужна помощь?</h3><p class="small muted">С вопросами по работе кабинета поможет техническая поддержка. О дальнейшем обучении можно поговорить с менеджером ВЭШ.</p></div><div class="row flex-wrap">${external(content.supportUrl,'Техническая поддержка','btn secondary')}${external(content.managerUrl,'Написать менеджеру','btn secondary')}</div><div class="school-contact">${external(content.schoolUrl,'Высшая экономическая школа')}<a href="mailto:${h(content.email)}">${h(content.email)}</a><a href="tel:+73433420110">${h(content.phone)}</a></div></section>`;
}
