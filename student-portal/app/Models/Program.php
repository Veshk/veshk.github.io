<?php

namespace App\Models;

use App\Support\CoursePage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saving(function (Program $program): void {
            if ($program->isDirty('page_blocks')) {
                CoursePage::validateAliases($program->page_blocks ?? [], null, auth()->user());
            }
        });
    }

    protected function casts(): array
    {
        return ['page_blocks' => 'array'];
    }

    public const SECTIONS = ['HR', 'Аналитика', 'КОРП', 'Маркетинг', 'Менеджмент', 'Продажи', 'Производство', 'Упр. навыки', 'Финансы', 'Центр', 'НЕДОСТУПНО'];

    public function studyGroups(): HasMany
    {
        return $this->hasMany(StudyGroup::class);
    }
}
