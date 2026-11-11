<?php

namespace App\Filament\Pages;

use App\Filament\Resources\StudyGroups\StudyGroupResource;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class StaffProfiles extends Page implements HasTable
{
    use InteractsWithTable;
    use WithFileUploads;

    protected string $view = 'filament.pages.staff-profiles';

    protected static ?string $title = 'Администраторы';

    protected static ?string $navigationLabel = 'Администраторы';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?int $navigationSort = 4;

    public ?int $selectedId = null;

    public string $contactText = '';

    public bool $reviewsUnassigned = false;

    public string $contactUrl = '';

    public ?TemporaryUploadedFile $photo = null;

    public bool $removePhoto = false;

    public int $photoPosition = 0;

    public static function canAccess(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    protected function editablePerson(int $id): User
    {
        abort_unless(static::canAccess(), 403);
        $person = User::whereIn('role', ['admin', 'senior'])->findOrFail($id);
        abort_unless(auth()->user()->isSenior() || auth()->id() === $person->id, 403);

        return $person;
    }

    public function table(Table $table): Table
    {
        return $table->query(User::query()->whereIn('role', ['admin', 'senior'])->withCount('studyGroups'))->defaultSort('name')->columns([
            ImageColumn::make('profile_photo')->label('Фото')->disk('public')->circular()->imageSize(36)
                ->extraImgAttributes(fn (User $record): array => ['style' => 'object-position: 50% '.(int) $record->profile_photo_position.'%']),
            TextColumn::make('name')->label('Администратор')->searchable(['name', 'email'])->sortable()->description(fn (User $record): string => $record->email)->action(fn (User $record) => $this->selectPerson($record->id)),
            TextColumn::make('role')->label('Роль аккаунта')->badge()->formatStateUsing(fn (string $state): string => $state === 'senior' ? 'Старший администратор' : 'Администратор'),
            TextColumn::make('study_groups_count')->label('Группы')->sortable()->url(fn (User $record): string => StudyGroupResource::getUrl('index', ['filters' => ['administrator_id' => ['value' => $record->id]]])),
            TextColumn::make('reviews_unassigned')->label('Заявки без группы')->badge()->formatStateUsing(fn (bool $state): string => $state ? 'Назначен ответственным' : 'Не назначен')->color(fn (bool $state): string => $state ? 'info' : 'gray'),
            TextColumn::make('contacts')->label('Контакты')->state(fn (User $record): string => filled($record->contact_text) || filled($record->contact_url) ? 'Заполнены' : 'Не заполнены')->color('gray'),
        ])->filters([
            TernaryFilter::make('reviews_unassigned')->label('Разбирает заявки без группы'),
            TernaryFilter::make('has_groups')->label('Есть группы')->queries(true: fn ($query) => $query->has('studyGroups'), false: fn ($query) => $query->doesntHave('studyGroups'), blank: fn ($query) => $query),
        ])->deferFilters(false)->recordActions([
            Action::make('profile')->label('Настроить профиль')->button()->color('gray')->action(fn (User $record) => $this->selectPerson($record->id)),
        ])->emptyStateHeading('Администраторы не найдены');
    }

    public function mount(): void
    {
        $this->selectPerson(auth()->id(), false);
    }

    public function selectPerson(int $id, bool $open = true): void
    {
        abort_unless(static::canAccess(), 403);
        $person = User::whereIn('role', ['admin', 'senior'])->findOrFail($id);
        $this->selectedId = $person->id;
        $this->contactText = $person->contact_text ?? '';
        $this->reviewsUnassigned = $person->reviews_unassigned;
        $this->contactUrl = $person->contact_url ?? '';
        $this->photo = null;
        $this->removePhoto = false;
        $this->photoPosition = (int) $person->profile_photo_position;
        $this->resetValidation();
        if ($open) {
            $this->dispatch('open-modal', id: 'staff-profile');
        }
    }

    public function save(): void
    {
        $person = $this->editablePerson((int) $this->selectedId);
        abort_if(! auth()->user()->isSenior() && $this->reviewsUnassigned !== $person->reviews_unassigned, 403);
        $this->validate([
            'reviewsUnassigned' => ['boolean'],
            'contactText' => ['string', 'max:2000'],
            'contactUrl' => ['nullable', 'url:http,https', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=5000,max_height=5000'],
            'photoPosition' => ['required', 'integer', 'between:0,100'],
        ]);
        $path = $person->profile_photo;
        if ($this->photo) {
            $path = $this->photo->store('staff-photos', 'public');
            abort_unless($path, 500);
        } elseif ($this->removePhoto) {
            $path = null;
        }
        $person->forceFill(['contact_text' => $this->contactText, 'contact_url' => $this->contactUrl ?: null, 'profile_photo' => $path, 'profile_photo_position' => $path ? $this->photoPosition : 0, 'reviews_unassigned' => auth()->user()->isSenior() ? $this->reviewsUnassigned : $person->reviews_unassigned])->save();
        $this->photo = null;
        $this->removePhoto = false;
        Notification::make()->title('Профиль и настройки сохранены')->success()->send();
        $this->dispatch('profile-saved');
        $this->dispatch('close-modal', id: 'staff-profile');
    }

    protected function getViewData(): array
    {
        return ['person' => User::whereIn('role', ['admin', 'senior'])->findOrFail($this->selectedId),
            'canEdit' => auth()->user()->isSenior() || auth()->id() === $this->selectedId];
    }
}
