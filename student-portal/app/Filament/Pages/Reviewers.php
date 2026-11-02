<?php

namespace App\Filament\Pages;

use App\Models\User;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class Reviewers extends Page
{
    protected string $view = 'filament.pages.reviewers';

    protected static ?string $title = 'Ответственные за помощь';

    protected static ?string $navigationLabel = 'Ответственные за помощь';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 3;

    public array $selected = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isSenior() ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->selected = User::whereIn('role', ['admin', 'senior'])->where('reviews_unassigned', true)->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validate(['selected' => ['array'], 'selected.*' => ['integer', 'distinct', 'exists:users,id']]);
        $staff = User::whereIn('role', ['admin', 'senior'])->get();
        abort_if(count(array_diff(array_map('intval', $this->selected), $staff->pluck('id')->all())) > 0, 422);
        DB::transaction(function () use ($staff): void {
            foreach ($staff as $person) {
                $person->forceFill(['reviews_unassigned' => in_array((string) $person->id, array_map('strval', $this->selected), true)])->save();
            }
        });
        Notification::make()->title('Ответственные сохранены')->success()->send();
    }
}
