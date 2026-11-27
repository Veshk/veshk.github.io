const headingKey = value => String(value || '').normalize('NFKC').replace(/\u00a0/g, ' ').replace(/[\s:]+$/g, '').trim().toLocaleLowerCase('ru');

export function cleanLessonText(value) {
  return String(value || '').replace(/\r\n?/g, '\n').replace(/[\u00a0\u2028]/g, ' ')
    .split('\n').map(line => line.trim()).join('\n').replace(/\n{3,}/g, '\n\n').trim();
}

/** Keep the original file index: access checks and stored files use it. */
export function organizeLessonMaterials(description, materials) {
  const cards = materials.map(item => ({ ...item, description: cleanLessonText(item.description), legacy: [] }));
  const intro = [];
  const extras = [];
  let target = intro;
  for (const line of cleanLessonText(description).split('\n')) {
    const key = headingKey(line);
    const matches = cards.filter(card => headingKey(card.title) === key);
    if (key && matches.length === 1) {
      target = matches[0].legacy;
      continue;
    }
    if (/^(список литературы|рекомендуемая литература|полезные ссылки|сервисы|серверы|инструкция)(\s|$)/i.test(line)) {
      const section = { title: /^серверы$/i.test(line) ? 'Полезные сервисы' : line, lines: [] };
      extras.push(section);
      target = section.lines;
      continue;
    }
    // These imported tab/download hints are replaced by the controls beside each file.
    if (/^\*?\s*файл доступен (?:для скачивания|для редактирования только после скачивания)\.?$/i.test(line)) continue;
    target.push(line.replace(/во вкладках «Ошибки» и «Ответ»/g, 'в блоках «Ошибки» и «Ответ» ниже')
      .replace(/во вкладке «([^»]+)»/g, 'в блоке «$1» ниже')
      .replace(/перейдите на вкладку "Инструкция"/g, 'смотрите блок «Инструкция» ниже'));
  }
  for (const card of cards) {
    const legacy = cleanLessonText(card.legacy.join('\n'));
    card.description = [legacy, card.description && card.description !== legacy ? card.description : ''].filter(Boolean).join('\n\n');
    delete card.legacy;
  }
  return { intro: cleanLessonText(intro.join('\n')), cards, extras: extras.map(s => ({ title: s.title, text: cleanLessonText(s.lines.join('\n')) })) };
}

function el(tag, className, text) {
  const element = document.createElement(tag);
  if (className) element.className = className;
  if (text) element.textContent = text;
  return element;
}

function appendLinkedText(parent, text) {
  const pattern = /https?:\/\/[^\s<>]+/g;
  let position = 0;
  for (const match of text.matchAll(pattern)) {
    const url = match[0].replace(/[),.;]+$/, '');
    parent.append(document.createTextNode(text.slice(position, match.index)));
    const link = el('a', 'lm-inline-link', url);
    link.href = url;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    parent.append(link);
    position = match.index + url.length;
  }
  parent.append(document.createTextNode(text.slice(position)));
}

function richText(text, bibliography = false) {
  const body = el('div', 'lm-copy');
  const blocks = cleanLessonText(text).split(/\n\n/).filter(Boolean);
  for (const block of blocks) {
    const lines = block.split('\n').filter(Boolean);
    const numbered = lines.every(line => /^\d+[.)]\s/.test(line));
    const bulleted = lines.every(line => /^[-•]\s/.test(line));
    if (bibliography || numbered || bulleted) {
      const list = el(numbered ? 'ol' : 'ul');
      for (const line of lines) {
        const item = el('li');
        appendLinkedText(item, numbered || bulleted ? line.replace(/^(?:\d+[.)]|[-•])\s*/, '') : line);
        list.append(item);
      }
      body.append(list);
    } else {
      const paragraph = el('p');
      lines.forEach((line, index) => {
        if (index) paragraph.append(el('br'));
        appendLinkedText(paragraph, line);
      });
      body.append(paragraph);
    }
  }
  return body;
}

export function enhanceLessonMaterials(root) {
  if (root.dataset.enhanced === 'true') return;
  const description = root.querySelector('[data-lesson-description]')?.textContent || '';
  const sources = [...root.querySelectorAll('[data-lesson-file]')];
  const materials = sources.map((node, index) => ({
    index,
    title: node.querySelector('[data-file-title]')?.textContent.trim() || 'Материал',
    description: node.querySelector('[data-file-description]')?.textContent || '',
    source: node,
  }));
  const organized = organizeLessonMaterials(description, materials);
  const fragment = document.createDocumentFragment();
  if (organized.intro) {
    const section = el('section', 'lm-assignment');
    const isTask = materials.length || /задани|практик/i.test(description);
    section.append(el('p', 'lm-kicker', isTask ? 'Практика' : 'К этому уроку'));
    section.append(el('h3', '', isTask ? 'Задание к уроку' : 'Дополнительно'));
    const text = organized.intro.replace(/^Практическое задание к курсу\n+/i, '');
    section.append(richText(text));
    fragment.append(section);
  }
  if (organized.cards.length) {
    const section = el('section', 'lm-materials');
    section.append(el('div', 'lm-section-heading'));
    section.firstChild.append(el('h3', '', 'Материалы к уроку'), el('span', 'lm-count', String(organized.cards.length)));
    const list = el('div', 'lm-file-list');
    for (const card of organized.cards) {
      const node = card.source.cloneNode(true);
      const oldDescription = node.querySelector('[data-file-description]');
      if (oldDescription) oldDescription.remove();
      const title = node.querySelector('[data-file-title]');
      const isAnswer = /^(ответ|ответы|ошибки|решение)$/i.test(card.title.trim());
      if (card.description) node.querySelector('.lm-file-actions').before(richText(card.description));
      if (isAnswer) {
        const details = el('details', 'lm-solution');
        const summary = el('summary');
        summary.append(el('span', '', card.title), el('span', 'lm-solution-hint', 'Для самопроверки'));
        details.append(summary, node);
        if (title) title.closest('.lm-file-heading').remove();
        list.append(details);
      } else list.append(node);
    }
    section.append(list);
    fragment.append(section);
  }
  for (const extra of organized.extras) {
    const section = el('section', 'lm-reading');
    section.append(el('h3', '', extra.title), richText(extra.text, /литератур/i.test(extra.title)));
    fragment.append(section);
  }
  root.replaceChildren(fragment);
  root.dataset.enhanced = 'true';
}

if (typeof document !== 'undefined') {
  document.querySelectorAll('[data-lesson-materials]').forEach(enhanceLessonMaterials);
}
