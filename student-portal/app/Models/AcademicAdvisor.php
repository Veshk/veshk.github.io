<?php

namespace App\Models;

use App\Support\CoursePage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class AcademicAdvisor extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function studyGroups(): HasMany
    {
        return $this->hasMany(StudyGroup::class);
    }

    public function photoUrl(): ?string
    {
        return $this->photo ? Storage::disk('public')->url($this->photo) : CoursePage::safeUrl($this->photo_url);
    }

    public function emailUrl(): ?string
    {
        return $this->email && filter_var($this->email, FILTER_VALIDATE_EMAIL) ? 'mailto:'.str_replace('%40', '@', rawurlencode($this->email)) : null;
    }
}
