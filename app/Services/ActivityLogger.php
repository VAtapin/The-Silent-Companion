<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    public static function write(string $action, ?Model $subject = null, ?string $description = null, array $old = [], array $new = []): void
    {
        ActivityLog::create([
            'user_id' => auth()->id(),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'action' => $action,
            'description' => $description,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
        ]);
    }
}
