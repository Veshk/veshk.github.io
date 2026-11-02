<?php

namespace App\Support;

use App\Models\StudyGroup;
use Carbon\Carbon;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class CoursePage
{
    public const TEMPLATES = ['mba' => 'MBA / длительная программа', 'short' => 'Короткая программа', 'corporate' => 'Корпоративная программа'];

    public const BLOCKS = ['contacts' => 'Контакты и чаты', 'schedule' => 'Расписание', 'join' => 'Подключиться к занятию', 'recordings' => 'Записи занятий', 'materials' => 'Материалы для обучения', 'info' => 'Информационный блок', 'video' => 'Видео о школе', 'introductions' => 'Практика «Знакомство»'];

    public static function defaults(string $template = 'mba'): array
    {
        $blocks = [];
        foreach (['contacts', 'schedule', 'join', 'recordings', 'materials'] as $type) {
            $blocks[] = ['type' => $type, 'data' => ['title' => self::BLOCKS[$type], 'visible' => true] + match ($type) {
                'contacts' => ['chats' => []],
                'schedule' => ['lessons' => [], 'show_moscow' => false, 'collapse_past' => true],
                'recordings' => ['videos' => []],
                'materials' => ['text' => 'Все материалы вашей группы собраны в одной папке.'],
                default => [],
            }];
        }
        if ($template === 'mba') {
            $blocks[] = ['type' => 'info', 'data' => ['title' => 'Подготовка к курсовой работе', 'text' => 'Информация о консультациях и материалы появятся здесь.', 'visible' => true]];
        }

        return $blocks;
    }

    public static function forGroup(StudyGroup $group): array
    {
        if ($group->page_blocks !== null) {
            return $group->page_blocks;
        }
        $blocks = self::defaults('short');
        if ($group->chat_url) {
            $blocks[0]['data']['chats'] = [['label' => 'Чат группы', 'url' => $group->chat_url]];
        }
        $blocks[1]['data']['lessons'] = array_map(function (array $lesson): array {
            $date = $lesson['date'] ?? '';
            if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $date)) {
                $date = Carbon::createFromFormat('d.m.Y', $date)->toDateString();
            }

            return array_replace($lesson, ['date' => $date, 'module' => $lesson['module'] ?? $lesson['subtitle'] ?? '']);
        }, $group->schedule ?? []);

        return $blocks;
    }

    public static function safeUrl(?string $value): ?string
    {
        return $value && filter_var($value, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($value, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true) ? $value : null;
    }

    public static function moscow(string $time): string
    {
        return preg_replace_callback('/(\d{1,2})[:.](\d{2})/', fn (array $m): string => sprintf('%02d:%s', ((int) $m[1] + 22) % 24, $m[2]), $time);
    }

    protected static function url(string $name, string $label): TextInput
    {
        return TextInput::make($name)->label($label)->trim()->url()->rules(['nullable', 'url:http,https'])->maxLength(2000);
    }

    public static function builder(): Builder
    {
        $blocks = [];
        foreach (self::BLOCKS as $type => $label) {
            $fields = [TextInput::make('title')->label('Заголовок блока')->required()->maxLength(180), Toggle::make('visible')->label('Показывать блок')->default(true)];
            $fields = array_merge($fields, match ($type) {
                'contacts' => [Textarea::make('text')->label('Описание')->maxLength(5000), self::url('contact_url', 'Ссылка для связи с администратором'), Repeater::make('chats')->label('Чаты группы')->schema([TextInput::make('label')->label('Название чата')->required()->maxLength(180), self::url('url', 'Ссылка')->required()])->defaultItems(0)->addActionLabel('Добавить чат')],
                'schedule' => [Toggle::make('show_moscow')->label('Показывать время Москвы рядом с Екатеринбургом'), Toggle::make('collapse_past')->label('Сворачивать полностью прошедшие месяцы')->default(true), self::url('source_url', 'Ссылка на исходное расписание')->helperText('Открывает Google Таблицу или файл. Занятия ниже заполняются отдельно; автоматический импорт будет следующим этапом.'), Repeater::make('lessons')->label('Занятия')->schema([
                    DatePicker::make('date')->label('Дата')->required(), TextInput::make('time')->label('Время ЕКБ')->placeholder('17:00–21:00')->required()->regex('/^([01]?\d|2[0-3])[:.][0-5]\d\s*[-–—]\s*([01]?\d|2[0-3])[:.][0-5]\d$/'), TextInput::make('title')->label('Занятие')->required()->maxLength(300), TextInput::make('teacher')->label('Преподаватель')->maxLength(180), TextInput::make('module')->label('Модуль')->maxLength(180), TextInput::make('format')->label('Формат занятия')->helperText('Можно указать онлайн для очной группы.')->maxLength(60), self::url('url', 'Ссылка для подключения'),
                ])->columns(2)->defaultItems(0)->addActionLabel('Добавить занятие')->collapsible()],
                'recordings' => [Repeater::make('videos')->label('Записи')->schema([TextInput::make('module')->label('Модуль')->maxLength(180), TextInput::make('title')->label('Название записи')->required()->maxLength(300), Textarea::make('text')->label('Описание')->maxLength(5000), self::url('url', 'Ссылка на запись')->required()])->defaultItems(0)->addActionLabel('Добавить запись')->collapsible()],
                'materials' => [Textarea::make('text')->label('Описание')->maxLength(5000), self::url('url', 'Ссылка на папку группы')->helperText('Папка открывается в новой вкладке.')],
                'join' => [TextInput::make('platform')->label('Платформа')->maxLength(100), Textarea::make('text')->label('Текст')->maxLength(5000), self::url('url', 'Ссылка для подключения'), TextInput::make('button')->label('Текст кнопки')->maxLength(100)],
                default => [Textarea::make('text')->label('Текст')->maxLength(5000), self::url('url', 'Ссылка'), TextInput::make('button')->label('Текст кнопки')->maxLength(100)],
            });
            $blocks[] = Block::make($type)->label($label)->schema($fields);
        }

        return Builder::make('page_blocks')->label('Блоки страницы')->blocks($blocks)->addActionLabel('Добавить блок')->reorderableWithButtons()->collapsible()->blockNumbers(false)->columnSpanFull();
    }
}
