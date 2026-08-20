<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Character extends Model
{
    protected $guarded = [];

    public function scenes(): BelongsToMany
    {
        return $this->belongsToMany(Scene::class);
    }

    public function assets(): MorphToMany
    {
        return $this->morphToMany(Asset::class, 'linkable', 'asset_links')->withTimestamps();
    }
}
