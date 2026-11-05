<?php

namespace App\Support;

use InvalidArgumentException;

class ScheduleImport
{
    private const HEADERS = ['date' => ['дата'], 'time' => ['время', 'время екб', 'время по екатеринбургу'], 'title' => ['тема', 'занятие', 'название занятия'], 'teacher' => ['преподаватель'], 'source_id' => ['id', 'id занятия', 'код занятия'], 'group' => ['тег группы', 'тэг группы'], 'module' => ['модуль'], 'format' => ['формат', 'формат занятия'], 'url' => ['ссылка', 'ссылка на занятие', 'ссылка для подключения'], 'hours' => ['часы', 'ак. часы']];

    private static function normalize(string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)));
    }

    public static function parse(string $text, string $groupTag = ''): array
    {
        if (strlen($text) > 2000000 || ! mb_check_encoding($text, 'UTF-8')) {
            throw new InvalidArgumentException('Нужен CSV/TSV в UTF-8, не более 2 МБ.');
        }
        if (preg_match('/^\s*</u', $text)) {
            throw new InvalidArgumentException('Вместо таблицы получена страница входа. Вставьте диапазон или загрузите CSV.');
        }
        $text = preg_replace('/^\x{FEFF}/u', '', $text);
        $rows = null;
        $header = 0;
        $map = [];
        foreach (["\t", ',', ';'] as $delimiter) {
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $text);
            rewind($stream);
            $candidate = [];
            while (($row = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
                $candidate[] = array_map(fn ($v): string => (string) $v, $row);
                if (count($candidate) > 2050 || count($row) > 60) {
                    fclose($stream);
                    throw new InvalidArgumentException('Не более 2000 занятий и 60 колонок.');
                }
            }
            fclose($stream);
            foreach (array_slice($candidate, 0, 30) as $index => $row) {
                $normalized = array_map(self::normalize(...), $row);
                $found = [];
                foreach (self::HEADERS as $key => $aliases) {
                    foreach ($normalized as $col => $value) {
                        if (in_array($value, $aliases, true)) {
                            $found[$key] = $col;
                            break;
                        }
                    }
                }
                if (isset($found['date'], $found['time'], $found['title'])) {
                    $rows = $candidate;
                    $map = $found;
                    $header = $index;
                    break 2;
                }
            }
        }
        if ($rows === null) {
            throw new InvalidArgumentException('Не найдены заголовки «Дата», «Время» и «Тема» (или «Занятие») в первых 30 строках.');
        }
        $lessons = $errors = $warnings = $ids = $identities = [];
        $skipped = 0;
        foreach (array_slice($rows, $header + 1, null, true) as $index => $row) {
            $get = fn (string $key): string => trim($row[$map[$key] ?? -1] ?? '');
            if (! array_filter($row, fn ($v) => trim($v) !== '')) {
                continue;
            }
            if (isset($map['group']) && $get('group') !== $groupTag) {
                $skipped++;

                continue;
            }
            if (self::normalize($get('date')) === 'дата' || ! array_filter(['date', 'time', 'title', 'teacher'], fn ($k) => $get($k) !== '')) {
                continue;
            }
            $line = $index + 1;
            $date = $get('date');
            if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $date, $m)) {
                $date = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
            }
            if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1]) || (int) $m[1] < 1900 || (int) $m[1] > 2200) {
                $errors[] = "Строка {$line}: нужна точная дата ДД.ММ.ГГГГ.";
            }
            $entry = ['date' => $date, 'title' => $get('title'), 'teacher' => $get('teacher')];
            if (! preg_match('/^([01]?\d|2[0-3])[:.]([0-5]\d)\s*[-–—]\s*([01]?\d|2[0-3])[:.]([0-5]\d)(?:\s+(.*))?$/u', $get('time'), $m)) {
                $errors[] = "Строка {$line}: время в формате 10:00–19:00 (ЕКБ).";
            } else {
                $entry['time'] = sprintf('%02d:%s–%02d:%s', $m[1], $m[2], $m[3], $m[4]);
                if ((int) $m[3] * 60 + (int) $m[4] <= (int) $m[1] * 60 + (int) $m[2]) {
                    $errors[] = "Строка {$line}: окончание должно быть позже начала в тот же день.";
                }
                if (! empty($m[5])) {
                    if ($entry['title'] === '') {
                        $entry['title'] = $m[5];
                        $warnings[] = "Строка {$line}: тема перенесена из ячейки времени. Проверьте её.";
                    } else {
                        $errors[] = "Строка {$line}: лишний текст в ячейке времени.";
                    }
                }
            }
            if ($entry['title'] === '') {
                $errors[] = "Строка {$line}: не указана тема занятия.";
            }
            foreach (['source_id', 'module', 'format', 'url', 'hours'] as $key) {
                if (isset($map[$key])) {
                    $entry[$key] = $get($key);
                }
            }
            if (isset($map['source_id'])) {
                if ($entry['source_id'] === '' || isset($ids[$entry['source_id']])) {
                    $errors[] = "Строка {$line}: пустой или повторяющийся ID занятия.";
                }
                $ids[$entry['source_id']] = true;
            }
            if (! empty($entry['url']) && ! CoursePage::safeUrl($entry['url'])) {
                $errors[] = "Строка {$line}: ссылка должна начинаться с https:// или http://.";
            }
            if (! empty($entry['hours']) && ! preg_match('/^\d{1,3}(?:[.,]\d{1,2})?$/', $entry['hours'])) {
                $errors[] = "Строка {$line}: часы должны быть числом.";
            }
            foreach (['title' => 300, 'teacher' => 180, 'module' => 180, 'format' => 60, 'url' => 2000, 'source_id' => 100] as $key => $limit) {
                if (mb_strlen($entry[$key] ?? '') > $limit) {
                    $errors[] = "Строка {$line}: слишком длинное значение {$key}.";
                }
            }
            $identity = json_encode([$entry['date'], $entry['time'] ?? '', $entry['title'], $entry['teacher']]);
            if (isset($identities[$identity])) {
                $errors[] = "Строка {$line}: занятие повторяется.";
            }
            $identities[$identity] = true;
            $lessons[] = $entry;
        }
        if (! $lessons) {
            $errors[] = isset($map['group']) ? 'Нет занятий с ТЕГом этой группы. Проверьте ТЕГ в источнике.' : 'В таблице нет занятий. Пустой импорт не удаляет расписание.';
        }
        if (count($lessons) > 2000) {
            $errors[] = 'Не более 2000 занятий за одно обновление.';
        }
        if (! isset($map['source_id'])) {
            $warnings[] = 'Нет колонки «ID занятия». При изменении даты, времени, темы или преподавателя строка считается новой. Для сохранения её ручных дополнений добавьте постоянный ID.';
        }

        return ['lessons' => $lessons, 'errors' => $errors, 'warnings' => $warnings, 'header' => $header + 1, 'skipped' => $skipped];
    }

    private static function key(array $row): string
    {
        return isset($row['source_id']) && $row['source_id'] !== '' ? 'id:'.$row['source_id'] : json_encode([$row['date'] ?? '', str_replace([' ', '-', '—', '.'], ['', '–', '–', ':'], $row['time'] ?? ''), $row['title'] ?? '', $row['teacher'] ?? '']);
    }

    public static function merge(array $parsed, array $current, string $mode = 'replace'): array
    {
        if (! in_array($mode, ['replace', 'append'], true)) {
            throw new InvalidArgumentException('Выберите способ обновления расписания.');
        }
        if ($mode === 'append') {
            $identity = fn (array $row): string => self::key(array_diff_key($row, ['source_id' => true]));
            $keys = array_fill_keys(array_map(self::key(...), $current), true);
            $identities = array_fill_keys(array_map($identity, $current), true);
            $added = array_values(array_filter($parsed['lessons'], fn ($row) => ! isset($keys[self::key($row)]) && ! isset($identities[$identity($row)])));
            $lessons = array_merge($current, $added);
            usort($lessons, fn ($a, $b) => strcmp(($a['date'] ?? '').($a['time'] ?? ''), ($b['date'] ?? '').($b['time'] ?? '')));
            if (count($lessons) > 2000) {
                $parsed['errors'][] = 'В блоке может быть не более 2000 занятий.';
            }

            return array_merge($parsed, ['mode' => $mode, 'lessons' => $lessons, 'counts' => ['added' => count($added), 'changed' => 0, 'same' => count($parsed['lessons']) - count($added), 'removed' => 0], 'removed' => []]);
        }
        $old = [];
        foreach ($current as $row) {
            $old[self::key($row)] = $row;
        }
        $counts = ['added' => 0, 'changed' => 0, 'same' => 0];
        $lessons = [];
        foreach ($parsed['lessons'] as $row) {
            $key = self::key($row);
            $match = $old[$key] ?? null;
            unset($old[$key]);
            $changed = $match && array_filter(array_keys($row), fn ($k) => (string) ($row[$k] ?? '') !== (string) ($match[$k] ?? ''));
            $counts[$match ? ($changed ? 'changed' : 'same') : 'added']++;
            $lessons[] = array_merge($match ?? [], $row);
        }

        return array_merge($parsed, ['mode' => $mode, 'lessons' => $lessons, 'counts' => $counts + ['removed' => count($old)], 'removed' => array_values($old)]);
    }
}
