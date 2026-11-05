<?php

namespace App\Filament\Resources\StudyGroups;

use App\Filament\Resources\StudyGroups\Pages\CreateStudyGroup;
use App\Filament\Resources\StudyGroups\Pages\EditGroupPage;
use App\Filament\Resources\StudyGroups\Pages\EditStudyGroup;
use App\Filament\Resources\StudyGroups\Pages\ListStudyGroups;
use App\Filament\Resources\StudyGroups\Pages\ViewStudyGroup;
use App\Models\Program;
use App\Models\StudyGroup;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StudyGroupResource extends Resource
{
    protected static ?string $model = StudyGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $navigationLabel = 'Группы';

    protected static ?string $modelLabel = 'группа';

    protected static ?string $pluralModelLabel = 'Группы';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Программа и группа')->schema([
                Select::make('program_id')->label('Программа из справочника')
                    ->relationship('program', 'title')->getOptionLabelFromRecordUsing(fn (Program $record): string => $record->tag.' — '.$record->title)
                    ->searchable(['title', 'tag'])->preload()->required()->exists('programs', 'id')->live()
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                        if (! filled($get('title')) && $state) {
                            $set('title', Program::find($state)?->title);
                        }
                        $set('code', $state ? Program::find($state)?->tag.'-'.trim($get('number') ?? '') : '');
                    })->helperText('Название можно уточнить для группы. Расписание и материалы у каждой группы свои.'),
                TextInput::make('title')->trim()->label('Название программы для группы')->required()->maxLength(180),
                TextInput::make('code')->label('ТЕГ группы — автоматически')->readOnly()->dehydrated(false)->helperText('ТЕГ программы + дефис + номер группы. Существующий ТЕГ меняется при изменении программы или номера.'),
                TextInput::make('number')->trim()->label('№ группы')->required()->maxLength(60)->live(onBlur: true)->afterStateUpdated(fn (Get $get, Set $set, ?string $state) => $set('code', (Program::find($get('program_id'))?->tag ?? '').'-'.trim($state ?? ''))),
                Select::make('group_type')->label('Тип группы')->options(array_combine(StudyGroup::TYPES, StudyGroup::TYPES)),
                Select::make('status')->label('Статус группы')->options(array_combine(StudyGroup::STATUSES, StudyGroup::STATUSES))->default('Запускается')->required()
                    ->helperText('Срок доступа определяется датами ниже. Статус используется для учёта.'),
                Select::make('administrator_id')->label('Администратор')->options(fn () => User::whereIn('role', ['admin', 'senior'])->pluck('name', 'id'))->required()
                    ->rules(['exists:users,id'])->disabled(fn (): bool => ! auth()->user()->isSenior())->dehydrated(fn (): bool => auth()->user()->isSenior()),
                Select::make('format')->label('Формат обучения')->options(['Очно' => 'Очно', 'Онлайн' => 'Онлайн', 'Смешанный' => 'Смешанный'])->default('Очно')->required(),
                TextInput::make('chat_url')->trim()->label('Чат группы')->url()->rules(['nullable', 'url:http,https'])->maxLength(2000)->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
            Section::make('Даты и доступ')->schema([
                DatePicker::make('starts_at')->label('Дата запуска')->required(),
                DatePicker::make('graduates_at')->label('Дата выпуска')->required()->afterOrEqual('starts_at'),
                TextInput::make('access_days')->label('Доступ после выпуска, дней')->numeric()->integer()->minValue(0)->maxValue(3650)->default(30)->required(),
                DatePicker::make('extended_until')->label('До какого числа продлён доступ')->afterOrEqual('graduates_at')
                    ->helperText('Для всей группы. Продление не сокращает основной срок.'),
                Toggle::make('registration_open')->label('Приём заявок открыт')->helperText('Группа появится в форме регистрации. Материалы доступны после одобрения.'),
            ])->columns(2)->columnSpanFull(),
            Section::make('Дополнительно')->schema([
                Textarea::make('description')->label('Приветствие для слушателей')->rows(3)->maxLength(3000)->columnSpanFull(),
                Textarea::make('admin_notes')->label('Комментарий администратора')->rows(3)->maxLength(3000)->helperText('Виден только сотрудникам.')->columnSpanFull(),
                Toggle::make('setup_completed')->label('Заполнение группы завершено'),
            ])->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('title')->label('Название программы для группы'),
            TextEntry::make('program.title')->label('Программа')->placeholder('Нужно выбрать программу'),
            TextEntry::make('program.tag')->label('ТЕГ программы'),
            TextEntry::make('program.section')->label('Раздел'),
            TextEntry::make('code')->label('ТЕГ группы'), TextEntry::make('number')->label('№ группы')->placeholder('Не указан'),
            TextEntry::make('status')->label('Статус группы'), TextEntry::make('group_type')->label('Тип группы'),
            TextEntry::make('administrator.name')->label('Администратор'),
            TextEntry::make('format')->label('Формат обучения'),
            TextEntry::make('chat_url')->label('Чат группы')->placeholder('Не указан')->url(fn (StudyGroup $record): ?string => $record->chat_url)->openUrlInNewTab(),
            TextEntry::make('access_scope')->label('Ваш доступ')->state(fn (StudyGroup $record): string => auth()->user()->can('update', $record) ? 'Редактирование доступно' : 'Только просмотр'),
            TextEntry::make('starts_at')->label('Дата запуска')->date('d.m.Y'), TextEntry::make('graduates_at')->label('Дата выпуска')->date('d.m.Y'),
            TextEntry::make('access_days')->label('Доступ после выпуска, дней'),
            TextEntry::make('archive_date')->label('Дата архивации по основному сроку')->state(fn (StudyGroup $record): string => $record->graduates_at->copy()->addDays($record->access_days)->format('d.m.Y')),
            TextEntry::make('extended_until')->label('До какого числа продлён доступ')->date('d.m.Y')->placeholder('Без продления'),
            TextEntry::make('closes')->label('Доступ до включительно')->state(fn (StudyGroup $record): string => $record->closesAt()->format('d.m.Y')),
            TextEntry::make('registration_open')->label('Приём заявок')->formatStateUsing(fn ($state): string => $state ? 'Открыт' : 'Закрыт'),
            TextEntry::make('students')->label('Количество слушателей')->state(fn (StudyGroup $record): int => $record->enrollments()->count()),
            TextEntry::make('setup_completed')->label('Заполнение завершено')->formatStateUsing(fn ($state): string => $state ? 'Да' : 'Нет'),
            TextEntry::make('admin_notes')->label('Комментарий администратора')->columnSpanFull(),
            TextEntry::make('description')->label('Приветствие')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->label('Название группы')->searchable(['title', 'code', 'number'])->sortable()->wrap()
                ->description(fn (StudyGroup $record): ?string => $record->program?->section),
            TextColumn::make('status')->label('Статус')->badge()->sortable()->color(fn (?string $state): string => match ($state) {
                'Запускается', 'Экскурсия' => 'info',
                'Идет обучение' => 'success',
                'На закрытии' => 'warning',
                'обучение отложено' => 'danger',
                'Продление доступа' => 'primary',
                default => 'gray',
            }),
            TextColumn::make('code')->label('ТЕГ группы')->sortable()->copyable(),
            TextColumn::make('number')->label('№')->sortable()->placeholder('—'),
            TextColumn::make('administrator.name')->label('Администратор')->searchable()->sortable()
                ->description(fn (StudyGroup $record): ?string => auth()->user()->can('update', $record) ? null : 'Только просмотр'),
            TextColumn::make('chat_url')->label('Чат')->formatStateUsing(fn (): string => 'Чат ↗')->placeholder('—')
                ->url(fn (StudyGroup $record): ?string => $record->chat_url)->openUrlInNewTab(),
            TextColumn::make('program.tag')->label('Программа')->searchable()->placeholder('Не выбрана')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('enrollments_count')->counts('enrollments')->label('Слушателей')->toggleable(isToggledHiddenByDefault: true),
            IconColumn::make('registration_open')->label('Приём заявок')->boolean()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('starts_at')->label('Запуск')->date('d.m.Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('graduates_at')->label('Выпуск')->date('d.m.Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('closes')->label('Доступ до')->state(fn (StudyGroup $record): string => $record->closesAt()->format('d.m.Y'))->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('format')->label('Формат')->toggleable(isToggledHiddenByDefault: true),
        ])->groups([
            Group::make('administrator.name')->label('Администратор')->collapsible(),
            Group::make('status')->label('Статус')->collapsible(),
        ])->defaultGroup('administrator.name')->defaultSort('title')->recordUrl(fn (StudyGroup $record): string => static::getUrl('view', ['record' => $record]))
            ->paginated([25, 50, 100])->searchPlaceholder('Название, ТЕГ, номер или администратор')
            ->filters([
                SelectFilter::make('status')->label('Статус группы')->options(array_combine(StudyGroup::STATUSES, StudyGroup::STATUSES)),
                SelectFilter::make('administrator_id')->label('Администратор')->relationship('administrator', 'name')->searchable()->preload(),
                SelectFilter::make('program_id')->label('Программа')->relationship('program', 'tag')->searchable()->preload(),
                Filter::make('mine')->label('Только мои группы')->query(fn (Builder $query): Builder => $query->where('administrator_id', auth()->id())),
            ])->deferFilters(false)->recordActions([
                Action::make('student')->label('Зайти в ЛК')->url(fn (StudyGroup $record): string => route('student.group', $record))->openUrlInNewTab(),
                Action::make('page')->label('Редактор')->visible(fn (StudyGroup $record): bool => auth()->user()->can('update', $record))->url(fn (StudyGroup $record): string => static::getUrl('page', ['record' => $record])),
                EditAction::make()->label('Настройки группы')->iconButton(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListStudyGroups::route('/'), 'create' => CreateStudyGroup::route('/create'), 'view' => ViewStudyGroup::route('/{record}'), 'edit' => EditStudyGroup::route('/{record}/edit'), 'page' => EditGroupPage::route('/{record}/page')];
    }
}
