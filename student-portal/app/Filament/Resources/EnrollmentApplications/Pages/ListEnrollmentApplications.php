<?php

namespace App\Filament\Resources\EnrollmentApplications\Pages;

use App\Filament\Resources\EnrollmentApplications\EnrollmentApplicationResource;
use Filament\Resources\Pages\ListRecords;

class ListEnrollmentApplications extends ListRecords
{
    protected static string $resource = EnrollmentApplicationResource::class;

    protected static ?string $title = 'Заявки на обучение';

    protected ?string $subheading = 'Проверьте принадлежность слушателя группе перед открытием доступа. Письма сохраняются только в тестовый перехватчик.';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
