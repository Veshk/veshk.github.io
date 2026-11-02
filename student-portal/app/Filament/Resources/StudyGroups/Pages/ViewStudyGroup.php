<?php

namespace App\Filament\Resources\StudyGroups\Pages;

use App\Filament\Resources\StudyGroups\StudyGroupResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewStudyGroup extends ViewRecord
{
    protected static string $resource = StudyGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Настройки группы'), Action::make('page')->label('Редактировать страницу ЛК')->visible(fn (): bool => auth()->user()->can('update', $this->getRecord()))->url(fn (): string => StudyGroupResource::getUrl('page', ['record' => $this->getRecord()])),
        ];
    }
}
