<?php

namespace App\Support;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;

class GroupHero
{
    public static function fields(): array
    {
        return [
            Section::make('Главный экран')->description('Обложка и приветствие только этой группы.')->schema([
                FileUpload::make('hero_image')->label('Обложка группы')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->disk('public')->directory('group-covers')->visibility('public')->preventFilePathTampering()->maxSize(2048)->rules(['dimensions:min_width=1200,min_height=450,max_width=8000,max_height=8000'])->helperText('JPG, PNG или WebP, до 2 МБ. Рекомендуем 2400 × 900 px, минимум 1200 × 450 px. Фото хорошего качества, без надписей; важные детали ближе к центру. На телефоне края обрезаются. Затемнение добавляется автоматически. Удаление вернёт стандартную обложку.'),
                Select::make('hero_position')->label('Положение изображения')->options(['center' => 'По центру', 'top' => 'По верхнему краю', 'bottom' => 'По нижнему краю'])->default('center')->required()->in(['center', 'top', 'bottom']),
                Select::make('academic_advisor_id')->label('Научный руководитель')->relationship('academicAdvisor', 'name')->searchable()->preload()->nullable()->exists('academic_advisors', 'id')->helperText('Профили добавляются в разделе «Научные руководители». Пустое поле скрывает карточку.'),
                Textarea::make('description')->label('Текст на главном экране')->rows(3)->maxLength(3000)->helperText('Напишите приветствие именно для этой группы. Оставьте поле пустым, чтобы скрыть текст.'),
            ])->columnSpanFull(),
        ];
    }
}
