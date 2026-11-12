<?php

namespace App\Models;

use Database\Factories\SelfStudyEnrollmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SelfStudyEnrollment extends Model
{
    /** @use HasFactory<SelfStudyEnrollmentFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime', 'completed_lessons' => 'array', 'notes' => 'array', 'history' => 'array'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(SelfStudyCourse::class, 'self_study_course_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function available(): bool
    {
        return ! $this->revoked_at && $this->starts_at->lte(now()) && $this->expires_at->gt(now()) && $this->course->is_published;
    }

    public function progress(): int
    {
        $ids = $this->course->lessons->where('is_published', true)->pluck('id')->all();

        return count($ids) ? (int) round(count(array_intersect($ids, $this->completed_lessons ?? [])) / count($ids) * 100) : 0;
    }

    public function statusLabel(): string
    {
        return $this->revoked_at ? 'Доступ закрыт' : ($this->available() ? 'Доступ открыт' : ($this->expires_at->lte(now()) ? 'Доступ завершён' : 'Курс готовится'));
    }
}
