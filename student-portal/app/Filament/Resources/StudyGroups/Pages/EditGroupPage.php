<?php

namespace App\Filament\Resources\StudyGroups\Pages;

use App\Filament\Pages\ImportSchedule;
use App\Filament\Resources\StudyGroups\StudyGroupResource;
use App\Support\CoursePage;
use App\Support\GroupHero;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditGroupPage extends EditRecord
{
    protected static string $resource = StudyGroupResource::class;

    protected static ?string $title = 'Редактировать страницу ЛК';

    public function form(Schema $schema): Schema
    {
        return $schema->components([...GroupHero::fields(), CoursePage::builder()->helperText('После сохранения откройте предпросмотр страницы группы.')]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['page_blocks'] = CoursePage::forEditor(CoursePage::forGroup($this->getRecord()));

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        CoursePage::validateAliases($data['page_blocks'] ?? [], $this->getRecord(), auth()->user());

        return ['page_blocks' => $data['page_blocks'] ?? [], 'hero_image' => $data['hero_image'] ?? null, 'hero_position' => $data['hero_position'] ?? 'center', 'description' => $data['description'] ?? null];
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('schedule')->label('Обновить расписание из таблицы')->url(fn (): string => ImportSchedule::getUrl(['group' => $this->getRecord()->id]))->openUrlInNewTab(), Action::make('preview')->label('Посмотреть страницу')->url(fn (): string => route('student.group', $this->getRecord()))->openUrlInNewTab(), Action::make('settings')->label('Настройки группы')->url(fn (): string => StudyGroupResource::getUrl('edit', ['record' => $this->getRecord()]))];
    }
}
