<?php

namespace App\Models;

use App\Models\Concerns\HasLocalizedContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicSiteSetting extends Model
{
    use HasLocalizedContent;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['official_links' => 'array'];
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'poster_asset_id');
    }
}
