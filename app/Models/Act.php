<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Act extends Model
{
    protected $guarded = [];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scenes(): HasMany
    {
        return $this->hasMany(Scene::class)->orderBy('number');
    }

    public function assets(): MorphToMany
    {
        return $this->morphToMany(Asset::class, 'linkable', 'asset_links')->withTimestamps();
    }
}
