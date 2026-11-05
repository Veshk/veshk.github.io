<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortalVisit extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'visited_at', 'page'];

    protected function casts(): array
    {
        return ['visited_at' => 'datetime'];
    }
}
