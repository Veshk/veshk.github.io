<?php

namespace App\Filament\Pages;

use App\Filament\Resources\EnrollmentApplications\EnrollmentApplicationResource;
use Filament\Pages\Dashboard;

class AdminHome extends Dashboard
{
    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        $this->redirect(EnrollmentApplicationResource::getUrl());
    }
}
