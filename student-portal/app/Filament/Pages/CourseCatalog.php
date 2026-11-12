<?php

namespace App\Filament\Pages;

use App\Models\SelfStudyCourse;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;

class CourseCatalog extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.course-catalog';

    #[Url]
    public string $kind = 'free';

    public static function canAccess(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    public function courseKind(): string
    {
        abort_unless(in_array($this->kind, ['free', 'recorded'], true), 404);

        return $this->kind;
    }

    public function getTitle(): string
    {
        return $this->courseKind() === 'free' ? 'Бесплатные курсы' : 'Курсы в записи';
    }

    public function table(Table $table): Table
    {
        return $table->modelLabel('курс')->pluralModelLabel('Курсы')->query(SelfStudyCourse::query()->visibleTo(auth()->user())->where('kind', $this->courseKind())->with('administrator')->withCount(['lessons', 'enrollments']))
            ->defaultSort('title')->columns([
                TextColumn::make('title')->label('Курс')->searchable()->wrap()->description(fn (SelfStudyCourse $record): ?string => $record->expert),
                TextColumn::make('is_published')->label('Публикация')->badge()->formatStateUsing(fn (bool $state): string => $state ? 'Опубликован' : 'Черновик'),
                TextColumn::make('lessons_count')->label('Уроков'),
                TextColumn::make('enrollments_count')->label('Слушателей')->url(fn (SelfStudyCourse $record): string => CourseAccesses::getUrl(['course' => $record->id])),
                TextColumn::make('administrator.name')->label('Администратор')->placeholder('Не назначен'),
            ])->headerActions([
                CreateAction::make()->model(SelfStudyCourse::class)->label('Добавить курс')->modalHeading('Добавить курс')->schema(fn (): array => $this->fields())
                    ->mutateDataUsing(fn (array $data): array => [...$data, 'kind' => $this->courseKind(), 'administrator_id' => auth()->user()->isSenior() ? ($data['administrator_id'] ?? auth()->id()) : auth()->id()]),
            ])->recordActions([
                EditAction::make()->label('Редактировать')->modalHeading('Редактировать курс')->schema(fn (): array => $this->fields())->modalWidth('5xl')->stickyModalHeader()->stickyModalFooter()->unsavedChangesAlert()
                    ->authorize(fn (SelfStudyCourse $record): bool => $record->canManage(auth()->user()))
                    ->mutateDataUsing(fn (array $data, SelfStudyCourse $record): array => [...$data, 'kind' => $record->kind, 'administrator_id' => auth()->user()->isSenior() ? ($data['administrator_id'] ?? null) : $record->administrator_id]),
                Action::make('participants')->label('Доступы')->url(fn (SelfStudyCourse $record): string => CourseAccesses::getUrl(['course' => $record->id])),
                Action::make('preview')->label('Открыть ЛК')->url(fn (SelfStudyCourse $record): string => route('student.course', $record))->openUrlInNewTab(),
            ])->emptyStateHeading('Курсов пока нет');
    }

    protected function fields(): array
    {
        return [
            TextInput::make('title')->label('Название курса')->required()->maxLength(255),
            TextInput::make('slug')->label('Код курса')->default(fn (): string => (string) Str::uuid())->required()->alphaDash()->unique(table: SelfStudyCourse::class, ignoreRecord: true)->maxLength(100)->helperText('Постоянный код для связи с формой на Тильде'),
            Select::make('administrator_id')->label('Администратор')->options(fn (): array => User::whereIn('role', ['admin', 'senior'])->pluck('name', 'id')->all())->visible(fn (): bool => auth()->user()->isSenior())->rules(['nullable', 'exists:users,id']),
            TextInput::make('expert')->label('Преподаватель')->maxLength(255),
            Textarea::make('description')->label('О курсе')->maxLength(5000),
            TextInput::make('landing_url')->label('Лендинг на Тильде')->url()->rules(['nullable', 'url:https'])->maxLength(2000),
            FileUpload::make('cover_path')->label('Обложка')->disk('public')->directory('course-covers')->preventFilePathTampering()->image()->maxSize(2048)->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),
            TextInput::make('price_minor')->label('Цена, ₽')->helperText('Изменение цены применяется к новым заказам')->numeric()->step(0.01)->minValue(1)->maxValue(1000000)->formatStateUsing(fn ($state) => $state ? $state / 100 : null)->dehydrateStateUsing(fn ($state): int => (int) round($state * 100))->required()->visible($this->courseKind() === 'recorded'),
            TextInput::make('access_days')->label('Доступ после оплаты, дней')->integer()->minValue(1)->maxValue(3650)->default(90)->required()->visible($this->courseKind() === 'recorded'),
            Toggle::make('is_published')->label('Курс опубликован')->default(false)->helperText('Слушатель увидит уроки только при открытом персональном доступе'),
            Repeater::make('lessons')->label('Уроки')->relationship()->orderColumn('position')->collapsed()->itemLabel(fn (array $state): string => $state['title'] ?? 'Новый урок')->addActionLabel('Добавить урок')->schema([
                TextInput::make('title')->label('Название урока')->required()->maxLength(255),
                TextInput::make('module_title')->label('Модуль')->maxLength(255),
                TextInput::make('duration_minutes')->label('Длительность, минут')->integer()->minValue(1)->maxValue(1440),
                Textarea::make('description')->label('Описание')->maxLength(10000),
                TextInput::make('video_url')->label('Ссылка на видео')->helperText('Kinescope: https://kinescope.io/идентификатор или ссылка Яндекс Диска')->rules(['nullable', 'regex:~^https://(?:kinescope\.io/[a-zA-Z0-9]+|disk\.yandex\.(?:ru|com)/[^\s]+)$~'])->maxLength(2000),
                Repeater::make('materials')->label('Материалы')->addActionLabel('Добавить материал')->collapsed()->itemLabel(fn (array $state): string => $state['title'] ?? 'Материал')->schema([
                    TextInput::make('title')->label('Название')->required()->maxLength(255),
                    FileUpload::make('path')->label('Файл')->disk('local')->directory('course-materials')->visibility('private')->preventFilePathTampering()->maxSize(51200)->acceptedFileTypes(['application/pdf', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'])->required(),
                ]),
                Toggle::make('is_published')->label('Урок опубликован')->default(false),
            ])->columnSpanFull(),
        ];
    }
}
