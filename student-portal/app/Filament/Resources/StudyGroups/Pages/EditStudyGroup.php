<?php

namespace App\Filament\Resources\StudyGroups\Pages;

use App\Filament\Resources\StudyGroups\StudyGroupResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditStudyGroup extends EditRecord
{
    public static bool $formActionsAreSticky = true;

    protected ?bool $hasUnsavedDataChangesAlert = true;

    protected static string $resource = StudyGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('page')->label('Редактировать страницу ЛК')->visible(fn (): bool => auth()->user()->can('update', $this->getRecord()))->url(fn (): string => StudyGroupResource::getUrl('page', ['record' => $this->getRecord()]))];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! auth()->user()->isSenior()) {
            unset($data['administrator_id']);
        }

        return $data;
    }
}
