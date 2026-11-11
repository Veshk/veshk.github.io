<?php

namespace App\Filament\Resources\Programs;

use App\Filament\Resources\Programs\Pages\CreateProgram;
use App\Filament\Resources\Programs\Pages\EditProgram;
use App\Filament\Resources\Programs\Pages\EditProgramTemplate;
use App\Filament\Resources\Programs\Pages\ListPrograms;
use App\Filament\Resources\Programs\Pages\ViewProgram;
use App\Filament\Resources\StudyGroups\StudyGroupResource;
use App\Models\Program;
use App\Support\CoursePage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProgramResource extends Resource
{
    protected static ?string $model = Program::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Программы';

    protected static ?string $modelLabel = 'программа';

    protected static ?string $pluralModelLabel = 'Программы';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([...static::settingsFields(), ...static::templateFields()]);
    }

    public static function settingsFields(): array
    {
        return [
            TextInput::make('tag')->trim()->label('ТЕГ программы')->required()->unique(ignoreRecord: true)->maxLength(60)
                ->helperText('Введите ТЕГ точно по регламенту школы, сохранив регистр и символы. Он не создаётся автоматически.'),
            TextInput::make('title')->trim()->label('Название программы')->required()->maxLength(180)
                ->helperText('При создании группы название можно уточнить. Изменение программы не переименовывает существующие группы.'),
            Select::make('section')->label('Раздел')->options(array_combine(Program::SECTIONS, Program::SECTIONS)),
            Select::make('program_type')->label('Тип программы')->options(Program::TYPES),
            Select::make('formats')->label('Формат')->multiple()->options(['онлайн' => 'Онлайн', 'офлайн' => 'Очно']),
            TextInput::make('hours')->label('Академические часы')->numeric()->minValue(1)->maxValue(65535),
            TextInput::make('certificate')->label('Документ')->maxLength(255),
            TextInput::make('website_url')->label('Программа на сайте школы')->url()->maxLength(2000),
        ];
    }

    public static function templateFields(): array
    {
        return [
            Select::make('template_key')->label('Базовый шаблон ЛК')->options(CoursePage::TEMPLATES)->default('mba')->required()->live()->afterStateUpdated(fn (Set $set, ?string $state) => $set('page_blocks', CoursePage::defaults($state ?? 'mba')))->helperText('Смена шаблона заменит базовые блоки программы для следующих групп. Созданные группы сохранят своё наполнение.'),
            CoursePage::builder()->default(CoursePage::defaults())->afterStateHydrated(function ($component, $state, ?Program $record): void {
                if ($state === null) {
                    $component->state(CoursePage::defaults($record?->template_key ?? 'mba'));
                }
            }),
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            ImageEntry::make('cover_url')->label('Обложка программы')->state(fn (Program $record): ?string => $record->coverUrl())->visible(fn (Program $record): bool => filled($record->coverUrl()))->imageHeight(220)->columnSpanFull(),
            TextEntry::make('tag')->label('ТЕГ программы'),
            TextEntry::make('title')->label('Название программы'),
            TextEntry::make('section')->label('Раздел')->placeholder('Не указан'),
            TextEntry::make('program_type')->label('Тип программы')->placeholder('Не указан'),
            TextEntry::make('formats')->label('Формат')->formatStateUsing(fn (string $state): string => $state === 'онлайн' ? 'Онлайн' : 'Очно')->listWithLineBreaks()->placeholder('Не указан'),
            TextEntry::make('hours')->label('Академические часы')->placeholder('Не указаны'),
            TextEntry::make('certificate')->label('Документ')->placeholder('Не указан'),
            TextEntry::make('website_url')->label('Программа на сайте школы')->url(fn (Program $record): ?string => preg_match('~^https?://~', $record->website_url ?? '') ? $record->website_url : null)->openUrlInNewTab()->placeholder('Не указана'),
            TextEntry::make('template_key')->label('Шаблон ЛК')->formatStateUsing(fn (?string $state): string => CoursePage::TEMPLATES[$state] ?? 'MBA / длительная программа'),
            TextEntry::make('groups_count')->label('Количество групп')->state(fn (Program $record): int => $record->studyGroups()->count()),
            TextEntry::make('studyGroups.code')->label('ТЕГи групп')->listWithLineBreaks()->placeholder('Групп пока нет'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('title')->recordUrl(null)->recordAction('view')->columns([
            ImageColumn::make('cover_url')->label('Обложка')->state(fn (Program $record): ?string => $record->coverUrl())->imageHeight(40),
            TextColumn::make('title')->label('Название программы')->searchable()->sortable()->wrap(),
            TextColumn::make('program_type')->label('Тип и формат')->formatStateUsing(fn (?string $state): string => Program::TYPES[$state] ?? $state ?? 'Не указан')->wrap()
                ->description(fn (Program $record): string => collect($record->formats ?? [])->map(fn (string $format): string => $format === 'онлайн' ? 'Онлайн' : 'Очно')->push($record->hours ? $record->hours.' ак. ч.' : null)->filter()->join(' ')),
            TextColumn::make('section')->label('Раздел')->badge(),
            TextColumn::make('tag')->label('ТЕГ программы')->searchable()->sortable(),
            TextColumn::make('study_groups_count')->counts('studyGroups')->label('Групп')->sortable()->url(fn (Program $record): string => StudyGroupResource::getUrl('index', ['filters' => ['program_id' => ['value' => $record->id]]])),
        ])->filters([
            SelectFilter::make('catalog')->label('Список')->options(['school' => 'Добавленные из каталога школы'])->query(fn (Builder $query, array $data): Builder => $query->when($data['value'] ?? null, fn (Builder $query): Builder => $query->whereNotNull('source_id'))),
            SelectFilter::make('program_type')->label('Тип программы')->options(Program::TYPES),
            SelectFilter::make('format')->label('Формат')->options(['онлайн' => 'Онлайн', 'офлайн' => 'Очно'])->query(fn (Builder $query, array $data): Builder => $query->when($data['value'] ?? null, fn (Builder $query, string $format): Builder => $query->whereJsonContains('formats', $format))),
            SelectFilter::make('section')->label('Раздел')->options(array_combine(Program::SECTIONS, Program::SECTIONS)),
        ])->deferFilters(false)->recordActions([
            ViewAction::make()->modal()->label('Карточка')->iconButton()->modalWidth('3xl')->stickyModalHeader()->stickyModalFooter(),
            EditAction::make()->modal()->label('Настройки')->button()->color('gray')->schema(fn (Schema $schema): Schema => $schema->columns(2)->components(static::settingsFields()))->modalWidth('3xl')->stickyModalHeader()->stickyModalFooter()->unsavedChangesAlert(),
            Action::make('template')->label('Шаблон ЛК')->button()->visible(fn (Program $record): bool => auth()->user()->can('update', $record))->url(fn (Program $record): string => static::getUrl('template', ['record' => $record])),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPrograms::route('/'), 'create' => CreateProgram::route('/create'), 'view' => ViewProgram::route('/{record}'), 'edit' => EditProgram::route('/{record}/edit'), 'template' => EditProgramTemplate::route('/{record}/template')];
    }
}
