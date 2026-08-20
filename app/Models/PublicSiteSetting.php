<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicSiteSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['official_links' => 'array'];
    }
}
