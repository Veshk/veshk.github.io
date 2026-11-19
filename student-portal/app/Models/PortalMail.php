<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortalMail extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'accepted_at' => 'datetime'];
    }
}
