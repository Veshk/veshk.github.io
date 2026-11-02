<?php

namespace App\Filament\Resources\EnrollmentApplications\Pages;

use App\Filament\Resources\EnrollmentApplications\EnrollmentApplicationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditEnrollmentApplication extends EditRecord
{
    protected static string $resource = EnrollmentApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
