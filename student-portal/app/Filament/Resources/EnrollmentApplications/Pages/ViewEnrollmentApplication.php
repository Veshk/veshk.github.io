<?php

namespace App\Filament\Resources\EnrollmentApplications\Pages;

use App\Filament\Resources\EnrollmentApplications\EnrollmentApplicationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEnrollmentApplication extends ViewRecord
{
    protected static string $resource = EnrollmentApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
