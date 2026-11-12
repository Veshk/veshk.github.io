<?php

namespace App\Models;

use Database\Factories\SelfStudyLessonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SelfStudyLesson extends Model
{
    /** @use HasFactory<SelfStudyLessonFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'materials' => 'array'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(SelfStudyCourse::class, 'self_study_course_id');
    }

    public function videoUrl(): ?string
    {
        return preg_match('~^https://(?:kinescope\.io/[a-zA-Z0-9]+|disk\.yandex\.(?:ru|com)/[^\s]+)$~', $this->video_url ?? '') ? $this->video_url : null;
    }

    public function embedUrl(): ?string
    {
        return preg_match('~^https://kinescope\.io/([a-zA-Z0-9]+)$~', $this->video_url ?? '', $matches) ? 'https://kinescope.io/embed/'.$matches[1].'?preload=false' : null;
    }
}
