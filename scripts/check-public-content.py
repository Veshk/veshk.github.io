"""Проверяет отсутствие рабочих ссылок и серверных данных в публичном снимке."""
from pathlib import Path
import re
import sys

root = Path(__file__).resolve().parents[1]
errors = []
for path in root.rglob('*'):
    if not path.is_file() or '.git' in path.parts:
        continue
    rel = str(path.relative_to(root))
    if path.name == '.env' or path.suffix.lower() in {'.sqlite', '.sqlite3', '.db', '.sql', '.pem', '.key', '.php'}:
        errors.append((rel, 'серверные данные или конфигурация'))
    if rel.startswith('student-portal/') and path.name not in {'README.md', 'index.html'}:
        errors.append((rel, 'серверный проект должен быть в закрытом репозитории'))
    if path.suffix not in {'.js', '.mjs', '.html', '.md', '.yml', '.json'}:
        continue
    text = path.read_text(encoding='utf-8')
    patterns = {
        'приватный ключ': r'-----BEGIN (?:RSA |OPENSSH |EC )?PRIVATE KEY-----',
        'GitHub token': r'\b(?:gh[pousr]_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{40,})\b',
        'Airtable token': r'\bpat[A-Za-z0-9]{14}\.[A-Za-z0-9]{40,}',
        'приглашение в чат': r'https?://(?:t\.me/\+|max\.ru/join/)',
        'Zoom с кодом доступа': r'https?://[^\s"<>]*zoom\.us/[^\s"<>]*[?&]pwd=',
        'данные рабочей конференции': r'(?i)(?:код доступа|идентификатор конференции)\s*[:=]\s*[0-9]',
    }
    for label, pattern in patterns.items():
        if re.search(pattern, text): errors.append((rel, label))
    if rel in {'lk/real-course-content.js', 'lk/free-courses/course-content.mjs'}:
        if re.search(r'https?://(?:[^/\s"<>]*zoom\.us|kinescope\.io|disk\.yandex\.ru|drive\.google\.com|docs\.google\.com|form\.asana\.com|app\.holst\.so|max\.ru|t\.me|clck\.ru|lk\.veshk\.ru)/', text):
            errors.append((rel, 'рабочая ссылка в демонстрационном наборе'))
        emails = re.findall(r'[\w.+-]{1,64}@[\w.-]{1,128}\.[A-Za-z]{2,16}', text)
        if any(not email.endswith('@example.test') for email in emails):
            errors.append((rel, 'реальный email в демонстрационном наборе'))

for rel, reason in errors:
    print(f'{rel}: {reason}')
if errors:
    sys.exit(1)
print('Публичный снимок проверен: серверные данные, ключи и рабочие учебные ссылки не обнаружены.')
