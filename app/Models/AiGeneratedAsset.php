<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiGeneratedAsset extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
}
