<?php

namespace App\Filament\Resources\StudyGroups\Pages;

use App\Filament\Resources\StudyGroups\StudyGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStudyGroups extends ListRecords
{
    protected static string $resource = StudyGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
