<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Shot extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_selected' => 'boolean'];
    }

    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function assets(): MorphToMany
    {
        return $this->morphToMany(Asset::class, 'linkable', 'asset_links')->withTimestamps();
    }
}
