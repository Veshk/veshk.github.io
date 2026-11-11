<?php

namespace App\Filament\Resources\AcademicAdvisors\Pages;

use App\Filament\Resources\AcademicAdvisors\AcademicAdvisorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAcademicAdvisors extends ManageRecords
{
    protected static string $resource = AcademicAdvisorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
