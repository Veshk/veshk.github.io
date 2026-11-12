<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class LearningManagement extends Page
{
    protected string $view = 'filament.pages.learning-management';

    protected static ?string $title = 'Управление обучением';

    public static function canAccess(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }
}
