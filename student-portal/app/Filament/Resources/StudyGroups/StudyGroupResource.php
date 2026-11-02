<?php

namespace App\Filament\Resources\StudyGroups;

use App\Filament\Resources\StudyGroups\Pages\CreateStudyGroup;
use App\Filament\Resources\StudyGroups\Pages\EditStudyGroup;
use App\Filament\Resources\StudyGroups\Pages\ListStudyGroups;
use App\Filament\Resources\StudyGroups\Pages\ViewStudyGroup;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StudyGroupResource extends Resource
{
    protected static ?string $model = StudyGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $navigationLabel = 'Курсы и группы';

    protected static ?string $modelLabel = 'группа';

    protected static ?string $pluralModelLabel = 'Курсы и группы';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Название программы')->required()->maxLength(180),
            TextInput::make('code')->label('Название / номер группы')->required()->unique(ignoreRecord: true)->maxLength(60),
            Select::make('administrator_id')->label('Администратор группы')->options(fn () => User::whereIn('role', ['admin', 'senior'])->pluck('name', 'id'))->required()->disabled(fn () => ! auth()->user()->isSenior())->dehydrated(fn () => auth()->user()->isSenior()),
            Select::make('format')->label('Формат')->options(['Очно' => 'Очно', 'Онлайн' => 'Онлайн', 'Смешанный' => 'Смешанный'])->default('Очно')->required(),
            DatePicker::make('starts_at')->label('Начало обучения')->required(),
            DatePicker::make('graduates_at')->label('Выпуск группы')->required()->afterOrEqual('starts_at'),
            TextInput::make('access_days')->label('Доступ после выпуска, дней')->numeric()->integer()->minValue(0)->maxValue(3650)->default(30)->required(),
            Toggle::make('registration_open')->label('Приём заявок открыт')->helperText('Группа появится в форме регистрации. Материалы остаются закрытыми до одобрения.'),
            Textarea::make('description')->label('Приветствие для слушателей')->rows(4)->maxLength(3000)->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('title')->label('Программа'), TextEntry::make('code')->label('Группа'),
            TextEntry::make('administrator.name')->label('Администратор'),
            TextEntry::make('access_scope')->label('Ваш доступ')->state(fn (StudyGroup $record) => auth()->user()->can('update', $record) ? 'Редактирование доступно' : 'Только просмотр'),
            TextEntry::make('starts_at')->label('Начало')->date('d.m.Y'), TextEntry::make('graduates_at')->label('Выпуск')->date('d.m.Y'),
            TextEntry::make('registration_open')->label('Приём заявок')->formatStateUsing(fn ($state) => $state ? 'Открыт' : 'Закрыт'),
            TextEntry::make('closes')->label('Доступ до')->state(fn (StudyGroup $record) => $record->closesAt()->format('d.m.Y')),
            TextEntry::make('description')->label('Приветствие')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->label('Программа')->searchable()->wrap()->description(fn (StudyGroup $record) => $record->code),
            TextColumn::make('administrator.name')->label('Администратор'),
            IconColumn::make('registration_open')->label('Приём заявок')->boolean(),
            TextColumn::make('starts_at')->label('Начало')->date('d.m.Y')->sortable(),
            TextColumn::make('scope')->label('Ваш доступ')->state(fn (StudyGroup $record) => auth()->user()->can('update', $record) ? 'Редактирование' : 'Только просмотр'),
        ])->recordActions([
            Action::make('view')->label('Карточка')->url(fn (StudyGroup $record) => static::getUrl('view', ['record' => $record])),
            EditAction::make()->label('Изменить'),
            Action::make('student')->label('Страница ЛК')->url(fn (StudyGroup $record) => route('student.group', $record))->openUrlInNewTab(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListStudyGroups::route('/'), 'create' => CreateStudyGroup::route('/create'), 'view' => ViewStudyGroup::route('/{record}'), 'edit' => EditStudyGroup::route('/{record}/edit')];
    }
}
