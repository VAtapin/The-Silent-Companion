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

    public function legalContent(string $page): string
    {
        $field = match ($page) {
            'impressum' => 'impressum',
            'datenschutz' => 'privacy_policy',
            default => throw new \InvalidArgumentException('Unknown legal page.'),
        };

        if (filled($this->{$field})) {
            return $this->{$field};
        }

        return file_get_contents(resource_path("legal/{$page}.de.md"));
    }
}
