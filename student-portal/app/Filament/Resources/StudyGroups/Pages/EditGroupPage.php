<?php

namespace App\Filament\Resources\StudyGroups\Pages;

use App\Filament\Pages\ImportSchedule;
use App\Filament\Resources\StudyGroups\StudyGroupResource;
use App\Support\CoursePage;
use App\Support\GroupHero;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Livewire\Attributes\Url;

class EditGroupPage extends EditRecord
{
    #[Url]
    public ?string $block = null;

    public static bool $formActionsAreSticky = true;

    protected bool $redirectToPortal = false;

    protected ?bool $hasUnsavedDataChangesAlert = true;

    protected static string $resource = StudyGroupResource::class;

    protected static ?string $title = 'Редактировать страницу ЛК';

    public function form(Schema $schema): Schema
    {
        return $schema->components([View::make('filament.components.course-editor-navigation')->columnSpanFull(), ...GroupHero::fields(), CoursePage::builder()->helperText('У каждого блока есть предпросмотр без сохранения. После сохранения можно вернуться к странице слушателя.')]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['page_blocks'] = CoursePage::forEditor(CoursePage::forGroup($this->getRecord()));

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        CoursePage::validateAliases($data['page_blocks'] ?? [], $this->getRecord(), auth()->user());

        return ['academic_advisor_id' => $data['academic_advisor_id'] ?? null, 'page_blocks' => $data['page_blocks'] ?? [], 'hero_image' => $data['hero_image'] ?? null, 'hero_position' => $data['hero_position'] ?? 'center', 'description' => $data['description'] ?? null];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            Action::make('saveAndOpen')->label('Сохранить и открыть ЛК')->color('gray')->action(function (): void {
                $this->redirectToPortal = true;
                $this->save();
            }),
            $this->getCancelFormAction(),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->redirectToPortal
            ? route('student.group', $this->getRecord()).($this->block ? '#course-block-'.rawurlencode($this->block) : '')
            : parent::getRedirectUrl();
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('returnToPage')->label('Вернуться к просмотру')->color('gray')->url(fn (): string => route('student.group', $this->getRecord()).($this->block ? '#course-block-'.rawurlencode($this->block) : '')), Action::make('schedule')->label('Обновить расписание из таблицы')->url(fn (): string => ImportSchedule::getUrl(['group' => $this->getRecord()->id]))->openUrlInNewTab(), Action::make('preview')->label('Посмотреть страницу')->url(fn (): string => route('student.group', $this->getRecord()))->openUrlInNewTab(), Action::make('settings')->label('Настройки группы')->url(fn (): string => StudyGroupResource::getUrl('edit', ['record' => $this->getRecord()]))];
    }
}
