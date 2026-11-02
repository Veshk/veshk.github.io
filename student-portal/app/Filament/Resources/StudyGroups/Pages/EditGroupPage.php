<?php

namespace App\Filament\Resources\StudyGroups\Pages;

use App\Filament\Pages\ImportSchedule;
use App\Filament\Resources\StudyGroups\StudyGroupResource;
use App\Support\CoursePage;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditGroupPage extends EditRecord
{
    protected static string $resource = StudyGroupResource::class;

    protected static ?string $title = 'Редактировать страницу ЛК';

    public function form(Schema $schema): Schema
    {
        return $schema->components([CoursePage::builder()->helperText('Это отдельная страница группы. Главный экран, ответы на вопросы и подвал входят в каждую страницу. После сохранения откройте предпросмотр.')]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['page_blocks'] = CoursePage::forGroup($this->getRecord());

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        CoursePage::validateAliases($data['page_blocks'] ?? [], $this->getRecord(), auth()->user());

        return ['page_blocks' => $data['page_blocks'] ?? []];
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('schedule')->label('Обновить расписание из таблицы')->url(fn (): string => ImportSchedule::getUrl(['group' => $this->getRecord()->id]))->openUrlInNewTab(), Action::make('preview')->label('Посмотреть страницу')->url(fn (): string => route('student.group', $this->getRecord()))->openUrlInNewTab(), Action::make('settings')->label('Настройки группы')->url(fn (): string => StudyGroupResource::getUrl('edit', ['record' => $this->getRecord()]))];
    }
}
