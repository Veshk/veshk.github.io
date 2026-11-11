<?php

namespace App\Filament\Resources\AcademicAdvisors;

use App\Filament\Resources\AcademicAdvisors\Pages\ManageAcademicAdvisors;
use App\Models\AcademicAdvisor;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AcademicAdvisorResource extends Resource
{
    protected static ?string $model = AcademicAdvisor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $navigationLabel = 'Научные руководители';

    protected static ?string $modelLabel = 'научный руководитель';

    protected static ?string $pluralModelLabel = 'Научные руководители';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('ФИО')->trim()->required()->maxLength(255),
            TextInput::make('email')->label('Почта для связи')->trim()->email()->maxLength(255)->helperText('Показывается слушателям как ссылка для письма.'),
            FileUpload::make('photo')->label('Фотография')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->disk('public')->directory('advisor-photos')->visibility('public')->preventFilePathTampering()->maxSize(2048)->rules(['dimensions:max_width=5000,max_height=5000']),
            TextInput::make('photo_url')->label('Ссылка на фото')->url()->rules(['nullable', 'url:http,https'])->maxLength(2000)->helperText('Можно указать ссылку вместо загрузки. Загруженный файл имеет приоритет.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('name')->columns([
            ImageColumn::make('photo')->label('Фото')->state(fn (AcademicAdvisor $record): ?string => $record->photoUrl())->circular(),
            TextColumn::make('name')->label('ФИО')->searchable()->sortable()->wrap(),
            TextColumn::make('email')->label('Почта')->placeholder('Не указана')->url(fn (AcademicAdvisor $record): ?string => $record->emailUrl()),
            TextColumn::make('study_groups_count')->label('Группы')->counts('studyGroups'),
        ])->recordActions([EditAction::make()->label('Редактировать')])->emptyStateHeading('Добавьте научного руководителя')->emptyStateDescription('Затем выберите его в редакторе страницы группы.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageAcademicAdvisors::route('/')];
    }
}
