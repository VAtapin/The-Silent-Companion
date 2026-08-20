<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsageRecord extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['usage_date' => 'date', 'cost' => 'decimal:6'];
    }
}
