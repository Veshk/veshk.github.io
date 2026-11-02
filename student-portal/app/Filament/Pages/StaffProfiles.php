<?php

namespace App\Filament\Pages;

use App\Models\User;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class StaffProfiles extends Page
{
    use WithFileUploads;

    protected string $view = 'filament.pages.staff-profiles';

    protected static ?string $title = 'Администраторы и фото';

    protected static ?string $navigationLabel = 'Администраторы и фото';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?int $navigationSort = 4;

    public ?int $selectedId = null;

    public string $contactText = '';

    public string $contactUrl = '';

    public ?TemporaryUploadedFile $photo = null;

    public bool $removePhoto = false;

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

    public function mount(): void
    {
        $this->selectPerson(auth()->id());
    }

    public function selectPerson(int $id): void
    {
        $person = $this->editablePerson($id);
        $this->selectedId = $person->id;
        $this->contactText = $person->contact_text ?? '';
        $this->contactUrl = $person->contact_url ?? '';
        $this->photo = null;
        $this->removePhoto = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $person = $this->editablePerson((int) $this->selectedId);
        $this->validate([
            'contactText' => ['string', 'max:2000'],
            'contactUrl' => ['nullable', 'url:http,https', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=5000,max_height=5000'],
        ]);
        $path = $person->profile_photo;
        if ($this->photo) {
            $path = $this->photo->store('staff-photos', 'public');
            abort_unless($path, 500);
        } elseif ($this->removePhoto) {
            $path = null;
        }
        $person->forceFill(['contact_text' => $this->contactText, 'contact_url' => $this->contactUrl ?: null, 'profile_photo' => $path])->save();
        $this->photo = null;
        $this->removePhoto = false;
        Notification::make()->title('Фото и контакты сохранены')->success()->send();
    }

    protected function getViewData(): array
    {
        return ['people' => User::whereIn('role', ['admin', 'senior'])->when(! auth()->user()->isSenior(), fn ($q) => $q->whereKey(auth()->id()))->orderBy('name')->get(),
            'person' => $this->editablePerson((int) $this->selectedId)];
    }
}
