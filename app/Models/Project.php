<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $guarded = [];

    public function acts(): HasMany
    {
        return $this->hasMany(Act::class)->orderBy('sort_order');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ChecklistSection::class)->whereNull('parent_id')->orderBy('sort_order');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ProjectVersion::class)->latest('version');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
