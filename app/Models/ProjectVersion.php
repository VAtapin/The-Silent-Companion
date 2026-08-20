<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }
}
