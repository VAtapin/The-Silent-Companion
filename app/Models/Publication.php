<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Publication extends Model
{
    public const STATUSES = ['Черновик', 'На проверке', 'Опубликовано', 'Скрыто', 'Архив'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'unpublished_at' => 'datetime', 'is_published' => 'boolean'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'publication_assets');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_published', true)->where('status', 'Опубликовано')->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))->whereNull('unpublished_at');
    }
}
