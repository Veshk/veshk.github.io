<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudyGroup extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['registration_open' => 'boolean', 'starts_at' => 'date', 'graduates_at' => 'date', 'access_days' => 'integer', 'schedule' => 'array', 'materials' => 'array'];
    }

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administrator_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(EnrollmentApplication::class);
    }

    public function closesAt(): Carbon
    {
        return $this->graduates_at->copy()->addDays($this->access_days)->endOfDay();
    }

    public function availableTo(User $user): bool
    {
        $enrollment = $this->enrollments()->where('user_id', $user->id)->first();
        if (! $enrollment) {
            return false;
        }
        $until = $this->closesAt();
        if ($enrollment->extended_until && $enrollment->extended_until->endOfDay()->gt($until)) {
            $until = $enrollment->extended_until->copy()->endOfDay();
        }

        return now()->lte($until);
    }
}
