<?php

namespace App\Filament\Resources\StudyGroups\Pages;

use App\Filament\Resources\StudyGroups\StudyGroupResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStudyGroups extends ListRecords
{
    protected static string $resource = StudyGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('studentPreview')->label('Как студент')->icon('heroicon-o-eye')->color('gray')->url(route('student.home', ['preview' => 1]))->openUrlInNewTab(),
            CreateAction::make()->label('Добавить группу')->modalHeading('Добавить группу')->createAnother(false)->modal()->modalWidth('3xl')->stickyModalHeader()->stickyModalFooter()->unsavedChangesAlert(),
        ];
    }
}
