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
        return ['page_blocks' => 'array', 'formats' => 'array', 'hours' => 'integer'];
    }

    public const TYPES = ['Программа проф. переподготовки' => 'Профпереподготовка', 'Программа повышения квалификации' => 'Повышение квалификации', 'Семинар-практикум' => 'Семинар-практикум', 'Специализированный курс' => 'Специализированный курс'];

    public function coverUrl(): ?string
    {
        $url = parse_url($this->cover_url ?? '');

        return is_array($url) && ($url['scheme'] ?? '') === 'https' && ($url['host'] ?? '') === 'veshk.ru' && ! isset($url['user']) && ! isset($url['pass']) && str_starts_with($url['path'] ?? '', '/media/images/') ? $this->cover_url : null;
    }

    public const SECTIONS = ['HR', 'Аналитика', 'КОРП', 'Маркетинг', 'Менеджмент', 'Продажи', 'Производство', 'Упр. навыки', 'Финансы', 'Центр', 'НЕДОСТУПНО'];

    public function studyGroups(): HasMany
    {
        return $this->hasMany(StudyGroup::class);
    }
}
