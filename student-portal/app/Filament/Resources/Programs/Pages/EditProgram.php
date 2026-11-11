<?php

namespace App\Filament\Resources\Programs\Pages;

use App\Filament\Resources\Programs\ProgramResource;
use App\Support\CoursePage;
use Filament\Resources\Pages\EditRecord;

class EditProgram extends EditRecord
{
    public static bool $formActionsAreSticky = true;

    protected ?bool $hasUnsavedDataChangesAlert = true;

    protected static string $resource = ProgramResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (is_array($data['page_blocks'] ?? null)) {
            $data['page_blocks'] = CoursePage::forEditor($data['page_blocks']);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
