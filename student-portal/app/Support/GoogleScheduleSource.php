<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class GoogleScheduleSource
{
    public static function csvUrl(string $value): string
    {
        $url = parse_url($value);
        if (! $url || ($url['scheme'] ?? '') !== 'https' || ($url['host'] ?? '') !== 'docs.google.com' || isset($url['user']) || isset($url['pass']) || isset($url['port']) || ! preg_match('~^/spreadsheets/d/(e/)?([A-Za-z0-9_-]+)(?:/[^/]*)?/?$~', $url['path'] ?? '', $m)) {
            throw new InvalidArgumentException('Нужна https-ссылка docs.google.com/spreadsheets/.');
        }
        parse_str($url['query'] ?? '', $query);
        parse_str($url['fragment'] ?? '', $fragment);
        $gid = $query['gid'] ?? $fragment['gid'] ?? '0';
        if (! is_string($gid) || ! ctype_digit($gid)) {
            throw new InvalidArgumentException('Неверный номер листа gid.');
        }

        return 'https://docs.google.com/spreadsheets/d/'.($m[1] ?? '').$m[2].(! empty($m[1]) ? '/pub?output=csv' : '/export?format=csv').'&gid='.$gid;
    }

    public function read(string $value): string
    {
        $url = self::csvUrl($value);
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $response = Http::connectTimeout(3)->timeout(12)->withoutRedirecting()->withOptions(['stream' => true])->get($url);
            if ($response->redirect()) {
                $location = $response->header('Location');
                $next = parse_url($location);
                $host = $next['host'] ?? '';
                if (($next['scheme'] ?? '') !== 'https' || isset($next['user']) || isset($next['pass']) || isset($next['port']) || ! ($host === 'docs.google.com' || str_ends_with($host, '.googleusercontent.com'))) {
                    throw new InvalidArgumentException('Источник требует входа в Google. Вставьте диапазон или загрузите CSV.');
                }
                $url = $location;

                continue;
            }
            if (! $response->successful() || str_contains(strtolower($response->header('Content-Type')), 'text/html')) {
                throw new InvalidArgumentException('Google не предоставил CSV. Для закрытой таблицы вставьте диапазон или загрузите CSV.');
            }
            $stream = $response->toPsrResponse()->getBody();
            $text = '';
            while (! $stream->eof()) {
                $text .= $stream->read(65536);
                if (strlen($text) > 2000000) {
                    $stream->close();
                    throw new InvalidArgumentException('Источник больше 2 МБ. Выберите лист нужной группы.');
                }
            }

            return $text;
        }
        throw new InvalidArgumentException('Слишком много перенаправлений Google. Вставьте диапазон или загрузите CSV.');
    }
}
