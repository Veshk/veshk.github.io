<?php

namespace App\Models;

use Database\Factories\SelfStudyCourseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class SelfStudyCourse extends Model
{
    /** @use HasFactory<SelfStudyCourseFactory> */
    use HasFactory;

    public const KINDS = ['free' => 'Бесплатный курс', 'recorded' => 'Курс в записи'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'access_days' => 'integer', 'price_minor' => 'integer'];
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(SelfStudyLesson::class)->orderBy('position')->orderBy('id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(SelfStudyEnrollment::class);
    }

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administrator_id');
    }

    public function canManage(User $user): bool
    {
        return $user->isSenior() || ($user->isStaff() && $this->administrator_id === $user->id);
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isSenior()) {
            $query->where('administrator_id', $user->isStaff() ? $user->id : -1);
        }
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? 'Курс';
    }

    public function coverUrl(): string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : asset('brand/group-placeholder-short.png');
    }
}
