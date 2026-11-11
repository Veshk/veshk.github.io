<?php

namespace App\Filament\Resources\Programs\Pages;

use App\Filament\Resources\Programs\ProgramResource;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;

class EditProgramTemplate extends EditProgram
{
    protected static ?string $title = 'Шаблон ЛК программы';

    public function form(Schema $schema): Schema
    {
        return $schema->components(ProgramResource::templateFields());
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return Arr::only($data, ['template_key', 'page_blocks']);
    }
}
