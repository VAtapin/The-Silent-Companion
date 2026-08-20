<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AiRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['cost' => 'decimal:6'];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function generatedAssets()
    {
        return $this->hasMany(AiGeneratedAsset::class, 'request_id');
    }

    public function usage()
    {
        return $this->hasOne(AiUsageRecord::class, 'request_id');
    }
}
