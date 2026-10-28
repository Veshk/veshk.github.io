const letters = {а:'a',б:'b',в:'v',г:'g',д:'d',е:'e',ё:'yo',ж:'zh',з:'z',и:'i',й:'y',к:'k',л:'l',м:'m',н:'n',о:'o',п:'p',р:'r',с:'s',т:'t',у:'u',ф:'f',х:'kh',ц:'ts',ч:'ch',ш:'sh',щ:'shch',ъ:'',ы:'y',ь:'',э:'e',ю:'yu',я:'ya'};
export function slug(value) {
  return [...value.toLowerCase().trim()].map(c => letters[c] ?? c).join('').normalize('NFKD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9_-]+/g, '_').replace(/_+/g, '_').replace(/^[_-]+|[_-]+$/g, '');
}
export function localDate(date = new Date()) {
  return `${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`;
}
export function contentDate(date) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return '';
  const [y,m,d] = date.split('-');
  const test = new Date(Number(y),Number(m)-1,Number(d));
  return localDate(test) === date ? `${d}.${m}.${y}` : '';
}
export function campaignName(type, theme, date) {
  const name = slug(theme);
  if (!name) return '';
  const month = date.slice(0,7).replace('-','_');
  const prefixes = {anons:'anons_',materials:'materials_mk_',webinar:'mk_',demo:'demo_',digest:'digest_',sale:'sale_',mono:'mono_',corp:'corp_',custom:''};
  return (prefixes[type] ?? '') + name + (['digest','sale','mono','corp'].includes(type) && month ? `_${month}` : '');
}
export function parseAddress(input) {
  let raw = input.trim();
  if (!raw) throw new Error('Вставьте адрес страницы, на которую хотите пригласить людей.');
  if (/[\s\\]/.test(raw)) throw new Error('В адресе есть пробел или обратная косая черта. Проверьте ссылку.');
  if (raw.startsWith('//')) raw = `https:${raw}`;
  else if (!/^[a-z][a-z\d+.-]*:/i.test(raw)) raw = `https://${raw}`;
  let url;
  try { url = new URL(raw); } catch { throw new Error('Не получилось распознать ссылку. Например: https://veshk.ru/calendar'); }
  if (!['https:', 'http:'].includes(url.protocol)) throw new Error('Подойдёт только ссылка на веб-страницу: https:// или http://.');
  if (url.username || url.password) throw new Error('Ссылка содержит логин или пароль. Используйте публичный адрес страницы.');
  if (!url.hostname.includes('.') || url.hostname.startsWith('.') || url.hostname.endsWith('.')) throw new Error('Проверьте адрес сайта. Например: veshk.ru');
  if (/\?utm_/i.test(url.search.slice(1))) throw new Error('В ссылке несколько знаков «?» перед метками. Вставьте исходный адрес страницы без старых меток.');
  const existing = [...url.searchParams.keys()].filter(k => /^utm_/i.test(k));
  return {url, existing, addedHttps:!/^https?:\/\//i.test(input.trim())};
}
export function buildLink(address, fields) {
  const parsed = parseAddress(address);
  const {url, existing} = parsed;
  for (const required of ['source','medium','campaign']) if (!String(fields[required] ?? '').trim()) throw new Error('Заполните источник, тип размещения и название кампании.');
  for (const key of existing) url.searchParams.delete(key);
  for (const key of ['source','medium','campaign','content','term']) {
    const value = String(fields[key] ?? '').trim();
    if (value) url.searchParams.set(`utm_${key}`, value);
  }
  return {...parsed, href:url.href};
}
export function csv(rows) {
  const escape = value => {
    let text = String(value ?? '');
    if (/^[\s]*[=+\-@]/.test(text)) text = `'${text}`;
    return `"${text.replace(/"/g,'""')}"`;
  };
  return '\uFEFF' + rows.map(row => row.map(escape).join(';')).join('\r\n');
}
