<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class ChecklistItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'due_date' => 'date', 'has_warning' => 'boolean'];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(ChecklistSection::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(ChecklistRequirement::class);
    }

    public function activeOverride(): HasOne
    {
        return $this->hasOne(ChecklistManualOverride::class)->where('is_active', true)->latestOfMany();
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(ChecklistManualOverride::class);
    }

    public function assets(): MorphToMany
    {
        return $this->morphToMany(Asset::class, 'linkable', 'asset_links')->withTimestamps();
    }
}
