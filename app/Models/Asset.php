<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use SoftDeletes;

    public const TYPES = ['Фото', 'Видео', 'Аудио', 'Документ', 'Текст', 'Ссылка'];

    public const STATUSES = ['Черновик', 'Загружено', 'Создано ИИ', 'На проверке', 'Утверждено', 'Отклонено', 'Требуется переснять', 'Используется в фильме', 'Финальная версия'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['captured_at' => 'date', 'has_usage_permission' => 'boolean', 'is_private' => 'boolean'];
    }

    public static function youtubeIdFromUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        $parts = parse_url(trim($url));
        if (! is_array($parts)) {
            return null;
        }
        $host = strtolower(preg_replace('/^www\./', '', $parts['host'] ?? ''));
        $path = trim($parts['path'] ?? '', '/');
        parse_str($parts['query'] ?? '', $query);
        $id = match ($host) {
            'youtu.be' => explode('/', $path)[0] ?? null,
            'youtube.com', 'm.youtube.com', 'music.youtube.com' => $query['v'] ?? (preg_match('#^(?:embed|shorts)/([^/]+)#', $path, $matches) ? $matches[1] : null),
            default => null,
        };

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? $id : null;
    }

    public function youtubeId(): ?string
    {
        return self::youtubeIdFromUrl($this->external_url);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function characters(): MorphToMany
    {
        return $this->morphedByMany(Character::class, 'linkable', 'asset_links')->withTimestamps();
    }

    public function locations(): MorphToMany
    {
        return $this->morphedByMany(Location::class, 'linkable', 'asset_links')->withTimestamps();
    }

    public function props(): MorphToMany
    {
        return $this->morphedByMany(Prop::class, 'linkable', 'asset_links')->withTimestamps();
    }

    public function acts(): MorphToMany
    {
        return $this->morphedByMany(Act::class, 'linkable', 'asset_links')->withTimestamps();
    }

    public function scenes(): MorphToMany
    {
        return $this->morphedByMany(Scene::class, 'linkable', 'asset_links')->withTimestamps();
    }

    public function shots(): MorphToMany
    {
        return $this->morphedByMany(Shot::class, 'linkable', 'asset_links')->withTimestamps();
    }

    public function checklistItems(): MorphToMany
    {
        return $this->morphedByMany(ChecklistItem::class, 'linkable', 'asset_links')->withTimestamps();
    }
}
