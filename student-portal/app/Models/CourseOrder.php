<?php

namespace App\Models;

use Database\Factories\CourseOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseOrder extends Model
{
    /** @use HasFactory<CourseOrderFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'amount_minor' => 'integer', 'access_days' => 'integer'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(SelfStudyCourse::class, 'self_study_course_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusLabel(): string
    {
        return ['pending' => 'Ожидает оплаты', 'paid' => 'Оплата подтверждена', 'refunded' => 'Возврат оформлен'][$this->status] ?? $this->status;
    }
}
