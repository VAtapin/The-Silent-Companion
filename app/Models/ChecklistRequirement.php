<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistRequirement extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'approved_only' => 'boolean', 'conditions' => 'array'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class, 'checklist_item_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }
}
