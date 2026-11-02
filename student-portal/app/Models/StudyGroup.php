<?php

namespace App\Models;

use App\Support\CoursePage;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class StudyGroup extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['registration_open' => 'boolean', 'setup_completed' => 'boolean', 'starts_at' => 'date', 'graduates_at' => 'date', 'extended_until' => 'date', 'access_days' => 'integer', 'schedule' => 'array', 'materials' => 'array', 'page_blocks' => 'array'];
    }

    protected static function booted(): void
    {
        static::saving(function (StudyGroup $group): void {
            if ((! $group->exists || $group->isDirty(['program_id', 'number'])) && $group->program_id && filled($group->number)) {
                $program = Program::findOrFail($group->program_id);
                $group->number = trim($group->number);
                $group->code = $program->tag.'-'.$group->number;
                if (static::where('code', $group->code)->when($group->exists, fn ($query) => $query->whereKeyNot($group->id))->exists()) {
                    throw ValidationException::withMessages(['data.number' => 'Группа с таким ТЕГом уже существует.']);
                }
            }
        });
        static::creating(function (StudyGroup $group): void {
            if ($group->program_id && $group->page_blocks === null) {
                $program = Program::findOrFail($group->program_id);
                $group->page_template = $program->template_key ?? 'mba';
                $group->page_blocks = $program->page_blocks ?? CoursePage::defaults($group->page_template);
            }
        });
    }

    public const STATUSES = ['Тех. группа', 'Запускается', 'Идет обучение', 'На закрытии', 'Архив', 'обучение отложено', 'Экскурсия', 'Продление доступа'];

    public const TYPES = ['ИНД, Личный кабинет', 'Сборное ОЧНО', 'Сборное ОНЛАЙН', 'Гибридный формат', 'Корп. обучение', 'Тех. параметр', 'КУРС В ЗАПИСИ', 'Экскурсия'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
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
        $until = $this->graduates_at->copy()->addDays($this->access_days)->endOfDay();

        return $this->extended_until && $this->extended_until->copy()->endOfDay()->gt($until)
            ? $this->extended_until->copy()->endOfDay()
            : $until;
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
