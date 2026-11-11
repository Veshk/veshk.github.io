<?php

namespace App\Filament\Resources\Programs\Pages;

use App\Filament\Resources\Programs\ProgramResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPrograms extends ListRecords
{
    protected static string $resource = ProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Добавить программу')->modalHeading('Добавить программу')->createAnother(false)->modal()->schema(ProgramResource::settingsFields())->modalWidth('3xl')->stickyModalHeader()->stickyModalFooter()->unsavedChangesAlert()];
    }
}
