import {materialCards,sampleMaterials} from './material-cards.mjs?v=20261127-materials-1';
export const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const h = escapeHtml;
const external = (url, label, className='') => `<a class="${className}" href="${h(url)}" target="_blank" rel="noopener">${h(label)} ↗</a>`;

export function kinescopeEmbed(url) {
  const match = /^https:\/\/kinescope\.io\/([A-Za-z0-9]+)$/.exec(url || '');
  return match ? `https://kinescope.io/embed/${match[1]}?preload=false` : null;
}

export function lessonMedia(course, index) {
  if(course.kind==='paid-recorded')return `<div class="video-placeholder paid-placeholder"><div class="video-message"><span class="play-circle" aria-hidden="true">▷</span><strong>Видеоурок — ${course.minutes[index]} мин</strong><p>Здесь будет запись урока внутри ЛК.</p><small>В публичном прототипе показана структура курса. Платное видео доступно только в защищённом кабинете.</small></div></div>`;
  const lesson = course.content?.lessons[index];
  if (!lesson) return '<div class="video-placeholder"><div class="video-message"><span class="play-circle" aria-hidden="true">▷</span><strong>Место для видеоурока</strong><p>Материалы этого курса ещё не перенесены. Можно проверить навигацию и анкету обратной связи.</p></div></div>';
  if (lesson.provider === 'Демонстрация') return `<div class="video-placeholder external-video"><div class="video-message"><span class="play-circle" aria-hidden="true">▷</span><strong>Пример урока — ${lesson.minutes} мин</strong><p>Рабочая запись доступна в закрытом кабинете. Здесь можно проверить переход к учебному ресурсу.</p>${external(lesson.url,'Открыть пример','btn')}</div></div>`;
  const embed = kinescopeEmbed(lesson.url);
  if (embed) return `<div class="lesson-player"><iframe data-kinescope-url="${h(lesson.url)}" data-embed-src="${h(embed)}" title="${h(lesson.title)} — видеоурок" allow="autoplay; fullscreen; picture-in-picture; encrypted-media" allowfullscreen></iframe><span class="player-loading" data-player-status role="status">Загружаем видео…</span></div><div class="media-caption"><span>${lesson.minutes} мин — Kinescope</span>${external(lesson.url,'Открыть видео отдельно')}</div>`;
  return `<div class="video-placeholder external-video"><div class="video-message"><span class="play-circle" aria-hidden="true">▷</span><strong>Видеоурок — ${lesson.minutes} мин</strong><p>Запись откроется в плеере Яндекс Диска.</p>${external(lesson.url,'Смотреть урок','btn')}</div></div>`;
}

export function courseMaterials(course) {
  const content = course.content;
  if(course.kind==='paid-recorded')return sampleMaterials();
  if (!content) return materialCards(course.practice || '', []);
  if (!content.practice) return '';
  const description=[content.practice.intro,...content.practice.steps.map((step,index)=>`${index+1}. ${step.title}\n${step.text}`)].join('\n\n');
  return materialCards(description,content.materials.map(file=>({name:file.name,url:file.url,demoResource:true})));
}

export function courseAbout(course) {
  const content = course.content;
  if (!content) return '';
  return `<details class="card course-about"><summary>Об эксперте — ${h(content.expert)}</summary><div class="expert-profile"><img src="${h(content.expertPhoto)}" alt="${h(content.expert)}" loading="lazy"><div><h3>${h(content.expert)}</h3><p>${h(content.expertRole)}</p><ul>${content.expertBio.map(text=>`<li>${h(text)}</li>`).join('')}</ul>${content.expertVideo?external(content.expertVideo,'Видео об эксперте','small'):''}</div></div></details><section class="card course-help"><div><p class="eyebrow">Мы рядом</p><h3>Нужна помощь?</h3><p class="small muted">С вопросами по работе кабинета поможет техническая поддержка. О дальнейшем обучении можно поговорить с менеджером ВЭШ.</p></div><div class="row flex-wrap">${external(content.supportUrl,'Техническая поддержка','btn secondary')}${external(content.managerUrl,'Написать менеджеру','btn secondary')}</div><div class="school-contact">${external(content.schoolUrl,'Высшая экономическая школа')}<a href="mailto:${h(content.email)}">${h(content.email)}</a></div></section>`;
}
