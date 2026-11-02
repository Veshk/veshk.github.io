<?php

namespace App\Filament\Resources\StudyGroups\Pages;

use App\Filament\Resources\StudyGroups\StudyGroupResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewStudyGroup extends ViewRecord
{
    protected static string $resource = StudyGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
