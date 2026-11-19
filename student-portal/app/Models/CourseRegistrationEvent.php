<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseRegistrationEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'processed_at' => 'datetime'];
    }
}
