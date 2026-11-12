<?php

namespace App\Support;

use App\Models\StudyGroup;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class GroupHero
{
    public static function fields(): array
    {
        return [
            Section::make('Главный экран')->id('course-editor-hero')->collapsed()->description('Обложка и приветствие только этой группы.')->headerActions([
                Action::make('previewHero')->label('Предпросмотр')->icon('heroicon-o-eye')->modalHeading('Предпросмотр главного экрана')->modalWidth('7xl')->modalSubmitAction(false)->modalCancelActionLabel('Продолжить редактирование')->modalContent(fn (Get $get, StudyGroup $record) => view('filament.components.group-hero-preview', ['html' => self::preview($record, ['hero_image' => $get('hero_image'), 'hero_position' => $get('hero_position'), 'description' => $get('description')])])),
            ])->schema([
                FileUpload::make('hero_image')->live()->label('Обложка группы')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->disk('public')->directory('group-covers')->visibility('public')->preventFilePathTampering()->maxSize(2048)->rules(['dimensions:min_width=1200,min_height=450,max_width=8000,max_height=8000'])->helperText('JPG, PNG или WebP, до 2 МБ. Рекомендуем 2400 × 900 px, минимум 1200 × 450 px. Фото хорошего качества, без надписей; важные детали ближе к центру. На телефоне края обрезаются. Затемнение добавляется автоматически. Удаление вернёт стандартную обложку.'),
                Select::make('hero_position')->label('Положение изображения')->options(['center' => 'По центру', 'top' => 'По верхнему краю', 'bottom' => 'По нижнему краю'])->default('center')->required()->in(['center', 'top', 'bottom'])->live(),
                Select::make('academic_advisor_id')->label('Научный руководитель')->relationship('academicAdvisor', 'name')->searchable()->preload()->nullable()->exists('academic_advisors', 'id')->helperText('Профили добавляются в разделе «Научные руководители». Пустое поле скрывает карточку.'),
                Textarea::make('description')->label('Текст на главном экране')->rows(3)->maxLength(3000)->live(debounce: 500)->helperText('Напишите приветствие именно для этой группы. Оставьте поле пустым, чтобы скрыть текст.'),
                View::make('filament.components.group-hero-preview')->viewData(fn (Get $get, StudyGroup $record): array => ['html' => self::preview($record, ['hero_image' => $get('hero_image'), 'hero_position' => $get('hero_position'), 'description' => $get('description')])])->columnSpanFull(),
            ])->columnSpanFull(),
        ];
    }

    public static function preview(StudyGroup $group, array $state): string
    {
        abort_unless(auth()->user()?->can('update', $group), 403);
        $image = $state['hero_image'] ?? null;
        if (is_array($image)) {
            $image = reset($image) ?: null;
        }
        $draft = clone $group;
        $draft->hero_image = is_string($image) && $image === $group->hero_image ? $image : null;
        $draft->hero_position = $state['hero_position'] ?? 'center';
        $draft->description = $state['description'] ?? '';
        $coverUrl = $image instanceof TemporaryUploadedFile && $image->isPreviewable() ? $image->temporaryUrl() : null;

        return view('student.hero-preview', ['group' => $draft, 'coverUrl' => $coverUrl])->render();
    }
}
