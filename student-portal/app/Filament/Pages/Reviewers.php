<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Reviewers extends Page
{
    protected string $view = 'filament.pages.reviewers';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->isSenior() ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->redirect(StaffProfiles::getUrl());
    }
}
