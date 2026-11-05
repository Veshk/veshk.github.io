<?php

namespace App\Filament\Resources\Programs;

use App\Filament\Resources\Programs\Pages\CreateProgram;
use App\Filament\Resources\Programs\Pages\EditProgram;
use App\Filament\Resources\Programs\Pages\ListPrograms;
use App\Filament\Resources\Programs\Pages\ViewProgram;
use App\Filament\Resources\StudyGroups\StudyGroupResource;
use App\Models\Program;
use App\Support\CoursePage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
        return $schema->components([
            TextInput::make('tag')->trim()->label('ТЕГ программы')->required()->unique(ignoreRecord: true)->maxLength(60)
                ->helperText('Введите ТЕГ точно по регламенту школы, сохранив регистр и символы. Он не создаётся автоматически.'),
            TextInput::make('title')->trim()->label('Название программы')->required()->maxLength(180)
                ->helperText('При создании группы название можно уточнить. Изменение программы не переименовывает существующие группы.'),
            Select::make('section')->label('Раздел')->options(array_combine(Program::SECTIONS, Program::SECTIONS)),
            Select::make('template_key')->label('Базовый шаблон ЛК')->options(CoursePage::TEMPLATES)->default('mba')->required()->live()->afterStateUpdated(fn (Set $set, ?string $state) => $set('page_blocks', CoursePage::defaults($state ?? 'mba')))->helperText('Смена шаблона заменит базовые блоки программы для следующих групп. Созданные группы сохранят своё наполнение.'),
            CoursePage::builder()->default(CoursePage::defaults())->afterStateHydrated(function ($component, $state, ?Program $record): void {
                if ($state === null) {
                    $component->state(CoursePage::defaults($record?->template_key ?? 'mba'));
                }
            }),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('tag')->label('ТЕГ программы'),
            TextEntry::make('title')->label('Название программы'),
            TextEntry::make('section')->label('Раздел')->placeholder('Не указан'),
            TextEntry::make('template_key')->label('Шаблон ЛК')->formatStateUsing(fn (?string $state): string => CoursePage::TEMPLATES[$state] ?? 'MBA / длительная программа'),
            TextEntry::make('groups_count')->label('Количество групп')->state(fn (Program $record): int => $record->studyGroups()->count()),
            TextEntry::make('studyGroups.code')->label('ТЕГи групп')->listWithLineBreaks()->placeholder('Групп пока нет'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('title')->recordUrl(fn (Program $record): string => static::getUrl('view', ['record' => $record]))->columns([
            TextColumn::make('tag')->label('ТЕГ программы')->searchable()->sortable(),
            TextColumn::make('title')->label('Название программы')->searchable()->sortable()->wrap(),
            TextColumn::make('section')->label('Раздел')->badge(),
            TextColumn::make('study_groups_count')->counts('studyGroups')->label('Групп')->sortable()->url(fn (Program $record): string => StudyGroupResource::getUrl('index', ['filters' => ['program_id' => ['value' => $record->id]]])),
        ])->filters([
            SelectFilter::make('section')->label('Раздел')->options(array_combine(Program::SECTIONS, Program::SECTIONS)),
        ])->deferFilters(false)->recordActions([
            Action::make('view')->label('Карточка')->url(fn (Program $record): string => static::getUrl('view', ['record' => $record])),
            EditAction::make()->label('Редактировать'),
            Action::make('groups')->label('Группы')->url(fn (Program $record): string => StudyGroupResource::getUrl('index', ['filters' => ['program_id' => ['value' => $record->id]]])),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPrograms::route('/'), 'create' => CreateProgram::route('/create'), 'view' => ViewProgram::route('/{record}'), 'edit' => EditProgram::route('/{record}/edit')];
    }
}
