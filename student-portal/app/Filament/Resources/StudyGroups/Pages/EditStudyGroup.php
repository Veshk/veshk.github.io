<?php

namespace App\Filament\Resources\StudyGroups\Pages;

use App\Filament\Resources\StudyGroups\StudyGroupResource;
use Filament\Resources\Pages\EditRecord;

class EditStudyGroup extends EditRecord
{
    protected static string $resource = StudyGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! auth()->user()->isSenior()) {
            unset($data['administrator_id']);
        }

        return $data;
    }
}
