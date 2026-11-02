<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public const SECTIONS = ['HR', 'Аналитика', 'КОРП', 'Маркетинг', 'Менеджмент', 'Продажи', 'Производство', 'Упр. навыки', 'Финансы', 'Центр', 'НЕДОСТУПНО'];

    public function studyGroups(): HasMany
    {
        return $this->hasMany(StudyGroup::class);
    }
}
