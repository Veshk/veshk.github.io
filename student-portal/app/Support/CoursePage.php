<?php

namespace App\Support;

use App\Filament\Resources\StudyGroups\StudyGroupResource;
use App\Models\StudyGroup;
use App\Models\User;
use Carbon\Carbon;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CoursePage
{
    public const TEMPLATES = ['mba' => 'MBA / длительная программа', 'short' => 'Короткая программа', 'corporate' => 'Корпоративная программа'];

    public const BLOCKS = ['contacts' => 'Контакты и чаты', 'schedule' => 'Расписание', 'join' => 'Подключиться к занятию', 'homework' => 'Домашние задания', 'recordings' => 'Записи занятий', 'materials' => 'Материалы для обучения', 'faq' => 'Вопросы об обучении', 'info' => 'Информационный блок', 'video' => 'Видео о школе', 'introductions' => 'Практика «Знакомство»', 'alias' => 'Общий блок из другого ЛК'];

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
            return self::withIds($group->page_blocks);
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

        return self::withIds($blocks);
    }

    public static function withIds(array $blocks): array
    {
        foreach ($blocks as $i => &$block) {
            $block['data']['block_id'] ??= 'legacy-'.$i;
        }

        return $blocks;
    }

    public static function forEditor(array $blocks): array
    {
        $blocks = self::withIds($blocks);
        foreach ($blocks as &$block) {
            if (($block['type'] ?? '') === 'recordings') {
                foreach ($block['data']['videos'] ?? [] as $j => $video) {
                    $block['data']['videos'][$j]['status'] = ($video['status'] ?? '') ?: (self::safeUrl($video['url'] ?? null) ? 'published' : 'pending');
                }
            }
        }

        return $blocks;
    }

    public static function display(StudyGroup $group): array
    {
        return array_map(function (array $block) use ($group): array {
            if (($block['type'] ?? '') !== 'alias') {
                return $block;
            }
            $source = StudyGroup::find($block['data']['source_group'] ?? null);
            $original = $source && $source->id !== $group->id ? collect(self::forGroup($source))->first(fn ($b) => ($b['data']['block_id'] ?? '') === ($block['data']['source_block'] ?? '') && $b['type'] !== 'alias' && ($b['data']['visible'] ?? true)) : null;
            if (! $original) {
                return ['type' => 'info', 'data' => ['title' => $block['data']['title'] ?? 'Общий блок', 'visible' => $block['data']['visible'] ?? true, 'text' => 'Содержимое временно недоступно. Администратор проверит подключение блока.']];
            }
            $original['data']['title'] = $block['data']['title'] ?? $original['data']['title'];
            $original['data']['visible'] = $block['data']['visible'] ?? true;
            $original['source_group_model'] = $source;

            return $original;
        }, self::forGroup($group));
    }

    public static function validateAliases(array $blocks, ?StudyGroup $target, ?User $user): void
    {
        foreach ($blocks as $block) {
            if (($block['type'] ?? '') !== 'alias') {
                continue;
            }
            $data = $block['data'];
            $unchanged = $target && collect($target->getOriginal('page_blocks') ?? [])->contains(fn ($old) => ($old['type'] ?? '') === 'alias' && (string) ($old['data']['source_group'] ?? '') === (string) ($data['source_group'] ?? '') && ($old['data']['source_block'] ?? '') === ($data['source_block'] ?? ''));
            if ($unchanged) {
                continue;
            }
            $source = StudyGroup::find($data['source_group'] ?? null);
            $exists = $source && collect(self::forGroup($source))->contains(fn ($b) => $b['type'] !== 'alias' && $b['data']['block_id'] === ($data['source_block'] ?? ''));
            if (! $source || ! $exists || $source->id === $target?->id || ($user && ! $user->can('update', $source))) {
                throw ValidationException::withMessages(['data.page_blocks' => 'Выберите исходный блок группы, которой вы управляете. Ссылка на другой общий блок или на эту же группу недопустима.']);
            }
        }
    }

    public static function references(int $sourceId, string $blockId): array
    {
        return StudyGroup::whereNotNull('page_blocks')->get()->filter(fn ($g) => collect($g->page_blocks)->contains(fn ($b) => ($b['type'] ?? '') === 'alias' && (string) ($b['data']['source_group'] ?? '') === (string) $sourceId && ($b['data']['source_block'] ?? '') === $blockId))->all();
    }

    public static function safeUrl(?string $value): ?string
    {
        return $value && filter_var($value, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($value, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true) ? $value : null;
    }

    /** @return list<array{linked: bool, parts: list<array{text: string, href: ?string}>}> */
    public static function contactLines(string $text, string $name): array
    {
        $normalize = fn (string $value): string => mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)));
        $pattern = "/[a-z0-9.!#$%&'*+\/=?^_`{|}~-]+@[a-z0-9-]+(?:\.[a-z0-9-]+)+|\+?\d[\d ()\x{00a0}.\-\x{2010}-\x{2015}]{7,}\d/iu";
        $lines = [];
        foreach (preg_split('/\r?\n/', $text) as $line) {
            $line = trim($line);
            if ($line === '' || $normalize($line) === $normalize($name)) {
                continue;
            }
            $line = preg_replace('/^(?:e-?mail|почта|тел(?:ефон)?\.?)\s*:\s*/iu', '', $line);
            preg_match_all($pattern, $line, $matches, PREG_OFFSET_CAPTURE);
            $parts = [];
            $last = 0;
            $linked = false;
            foreach ($matches[0] as [$value, $offset]) {
                $parts[] = ['text' => substr($line, $last, $offset - $last), 'href' => null];
                $digits = preg_replace('/\D/', '', $value);
                $href = null;
                if (str_contains($value, '@')) {
                    $href = 'mailto:'.str_replace('%40', '@', rawurlencode($value));
                } elseif (strlen($digits) >= 10 && strlen($digits) <= 15) {
                    $href = 'tel:'.(strlen($digits) === 11 && str_starts_with($digits, '8') ? '+7'.substr($digits, 1) : (str_starts_with($value, '+') ? '+' : '').$digits);
                }
                $parts[] = ['text' => $value, 'href' => $href];
                $linked = $linked || $href !== null;
                $last = $offset + strlen($value);
            }
            $parts[] = ['text' => substr($line, $last), 'href' => null];
            $lines[] = ['linked' => $linked, 'parts' => $parts];
        }

        return $lines;
    }

    /** @return list<array<string, mixed>> */
    public static function upcoming(array $blocks, ?Carbon $now = null): array
    {
        $clock = ($now ?? now())->copy()->setTimezone('Asia/Yekaterinburg');
        $today = $clock->toDateString();
        $minute = $clock->hour * 60 + $clock->minute;
        $rows = [];
        foreach ($blocks as $index => $block) {
            if (($block['type'] ?? '') !== 'schedule' || ! ($block['data']['visible'] ?? true)) {
                continue;
            }
            foreach ($block['data']['lessons'] ?? [] as $lesson) {
                $date = $lesson['date'] ?? '';
                if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
                    continue;
                }
                $rows[] = $lesson + ['show_moscow' => $block['data']['show_moscow'] ?? false, 'section' => $index];
            }
        }
        usort($rows, fn (array $a, array $b): int => strcmp($a['date'], $b['date']) ?: ((self::timeMinutes($a['time'] ?? '')[0] ?? 1440) <=> (self::timeMinutes($b['time'] ?? '')[0] ?? 1440)));
        $nextDate = null;
        foreach ($rows as $lesson) {
            $time = self::timeMinutes($lesson['time'] ?? '');
            if ($lesson['date'] > $today || ($lesson['date'] === $today && (! $time || $time[1] > $minute))) {
                $nextDate = $lesson['date'];
                break;
            }
        }

        return array_values(array_filter($rows, fn (array $lesson): bool => $lesson['date'] === $nextDate));
    }

    /** @return array{int, int}|null */
    protected static function timeMinutes(string $value): ?array
    {
        if (! preg_match('/^(\d{1,2})[:.](\d{2})\s*[-–—]\s*(\d{1,2})[:.](\d{2})$/u', $value, $m) || $m[1] > 23 || $m[3] > 23 || $m[2] > 59 || $m[4] > 59) {
            return null;
        }

        return [(int) $m[1] * 60 + (int) $m[2], (int) $m[3] * 60 + (int) $m[4]];
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
            $fields = [Hidden::make('block_id')->default(fn (): string => (string) Str::uuid()), TextInput::make('title')->label('Заголовок блока')->required()->maxLength(180), Toggle::make('visible')->label('Показывать блок')->default(true), Text::make(function (Get $get, $record): HtmlString {
                if (! $record instanceof StudyGroup) {
                    return new HtmlString('');
                }
                $links = array_map(fn ($g) => '<a href="'.e(StudyGroupResource::getUrl('page', ['record' => $g])).'">'.e($g->title.' / '.$g->code).'</a>', self::references($record->id, $get('block_id') ?? ''));

                return new HtmlString($links ? '<div>Этот блок используется в других ЛК: '.implode(', ', $links).'. Изменение, скрытие или удаление повлияет на подключённые блоки.</div>' : '');
            })];
            $fields = array_merge($fields, match ($type) {
                'contacts' => [Text::make('Фото и общие контакты: Панель администратора → Администраторы и фото.'), Textarea::make('text')->label('Описание')->maxLength(5000), self::url('contact_url', 'Ссылка для связи с администратором'), Textarea::make('chat_text')->label('Описание чатов')->default('Присоединяйтесь к чатам, чтобы быть на связи с группой и получать объявления.')->maxLength(5000), Repeater::make('chats')->label('Чаты группы')->schema([TextInput::make('label')->label('Название чата')->required()->maxLength(180), self::url('url', 'Ссылка')->required()])->defaultItems(0)->addActionLabel('Добавить чат')],
                'alias' => [
                    Select::make('source_group')->label('ЛК-источник')->options(fn (): array => StudyGroup::query()->when(! auth()->user()?->isSenior(), fn ($q) => $q->where('administrator_id', auth()->id()))->get()->mapWithKeys(fn ($g) => [$g->id => $g->title.' — '.$g->code])->all())->required()->live()->afterStateUpdated(fn ($set) => $set('source_block', null)),
                    Select::make('source_block')->label('Исходный блок')->options(fn (Get $get): array => ($source = StudyGroup::find($get('source_group'))) ? collect(self::forGroup($source))->filter(fn ($b) => $b['type'] !== 'alias')->mapWithKeys(fn ($b) => [$b['data']['block_id'] => $b['data']['title'] ?? self::BLOCKS[$b['type']]])->all() : [])->required(),
                    Text::make(function (Get $get): HtmlString {
                        $source = StudyGroup::find($get('source_group'));

                        return new HtmlString($source ? 'Источник: <a href="'.e(StudyGroupResource::getUrl('page', ['record' => $source])).'">'.e($source->title.' / '.$source->code).'</a>. Содержимое обновляется из этого ЛК.' : 'Выберите источник. Здесь меняются только заголовок, порядок и видимость подключения.');
                    }),
                ],
                'schedule' => [Toggle::make('show_moscow')->label('Показывать время Москвы рядом с Екатеринбургом'), Toggle::make('collapse_past')->label('Сворачивать полностью прошедшие месяцы')->default(true), self::url('source_url', 'Ссылка на исходное расписание')->helperText('Для загрузки занятий используйте «Обновить расписание из таблицы» над редактором группы. Сначала сохраните изменения страницы.'), Repeater::make('lessons')->label('Занятия')->schema([
                    TextInput::make('source_id')->label('ID занятия для обновления из таблицы')->maxLength(100), TextInput::make('hours')->label('Академические часы')->maxLength(10), DatePicker::make('date')->label('Дата')->required(), TextInput::make('time')->label('Время ЕКБ')->placeholder('17:00–21:00')->required()->regex('/^([01]?\d|2[0-3])[:.][0-5]\d\s*[-–—]\s*([01]?\d|2[0-3])[:.][0-5]\d$/u'), TextInput::make('title')->label('Занятие')->required()->maxLength(300), TextInput::make('teacher')->label('Преподаватель')->maxLength(180), TextInput::make('module')->label('Модуль')->maxLength(180), TextInput::make('format')->label('Формат занятия')->helperText('Можно указать онлайн для очной группы.')->maxLength(60), self::url('url', 'Ссылка для подключения'),
                ])->columns(2)->defaultItems(0)->addActionLabel('Добавить занятие')->collapsible()],
                'homework' => [Text::make('Статус общий для группы, а не персональная отметка выполнения.'), Repeater::make('tasks')->label('Задания')->schema([
                    TextInput::make('module')->label('Модуль')->maxLength(300), DatePicker::make('deadline')->label('Срок'), Select::make('status')->label('Статус задания')->options(['upcoming' => 'Предстоит', 'past' => 'Прошедшее'])->default('upcoming')->required(), Textarea::make('title')->label('Задание')->required()->maxLength(5000), self::url('url', 'Ссылка на материалы'), TextInput::make('button')->label('Текст ссылки')->maxLength(100),
                ])->defaultItems(0)->addActionLabel('Добавить задание')->collapsible()],
                'faq' => [Repeater::make('questions')->label('Вопросы и ответы')->schema([
                    TextInput::make('question')->label('Вопрос')->required()->maxLength(500), Textarea::make('answer')->label('Ответ')->required()->maxLength(5000), self::url('url', 'Ссылка в ответе'), TextInput::make('button')->label('Текст ссылки')->maxLength(100),
                ])->defaultItems(0)->addActionLabel('Добавить вопрос')->collapsible()],
                'recordings' => [Repeater::make('videos')->label('Записи')->schema([
                    TextInput::make('module')->label('Модуль')->maxLength(300), TextInput::make('title')->label('Название записи')->required()->maxLength(300), DatePicker::make('date')->label('Дата занятия'), Select::make('status')->label('Статус записи')->options(['pending' => 'Видео ещё не опубликовано', 'published' => 'Запись опубликована'])->default('pending')->required()->live(), Textarea::make('text')->label('Описание')->maxLength(5000), self::url('url', 'Ссылка на запись')->required(fn (Get $get): bool => $get('status') === 'published'),
                ])->defaultItems(0)->addActionLabel('Добавить запись')->collapsible()],
                'materials' => [Textarea::make('text')->label('Описание')->maxLength(5000), self::url('url', 'Ссылка на папку группы (необязательно)'), Repeater::make('resources')->label('Материалы')->schema([
                    TextInput::make('title')->label('Название материала')->required()->maxLength(300), Textarea::make('text')->label('Описание')->maxLength(5000), self::url('url', 'Ссылка на материал')->required(), TextInput::make('button')->label('Текст кнопки')->maxLength(100),
                ])->defaultItems(0)->addActionLabel('Добавить материал')->collapsible()],
                'video', 'introductions' => [Textarea::make('text')->label('Текст')->maxLength(5000), self::url('url', 'Ссылка'), TextInput::make('button')->label('Текст кнопки')->maxLength(100), self::url('image_url', 'Ссылка на изображение (необязательно)'), TextInput::make('image_alt')->label('Описание изображения')->maxLength(300)],
                'join' => [TextInput::make('platform')->label('Платформа')->maxLength(100), Textarea::make('text')->label('Текст')->maxLength(5000), self::url('url', 'Ссылка для подключения'), TextInput::make('button')->label('Текст кнопки')->maxLength(100)],
                default => [Textarea::make('text')->label('Текст')->maxLength(5000), self::url('url', 'Ссылка'), TextInput::make('button')->label('Текст кнопки')->maxLength(100)],
            });
            $blocks[] = Block::make($type)->label($label)->schema($fields);
        }

        return Builder::make('page_blocks')->label('Блоки страницы')->blocks($blocks)->addActionLabel('Добавить блок')->reorderableWithButtons()->collapsible()->blockNumbers(false)->columnSpanFull();
    }
}
