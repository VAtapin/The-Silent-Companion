<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Scene extends Model
{
    protected $guarded = [];

    public function act(): BelongsTo
    {
        return $this->belongsTo(Act::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function shots(): HasMany
    {
        return $this->hasMany(Shot::class)->orderBy('number');
    }

    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class);
    }

    public function props(): BelongsToMany
    {
        return $this->belongsToMany(Prop::class);
    }

    public function assets(): MorphToMany
    {
        return $this->morphToMany(Asset::class, 'linkable', 'asset_links')->withTimestamps();
    }
}
