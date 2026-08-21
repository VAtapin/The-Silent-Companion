<?php

namespace App\Http\Controllers;

use App\Models\Act;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\Character;
use App\Models\ChecklistItem;
use App\Models\DonationSetting;
use App\Models\Location;
use App\Models\Project;
use App\Models\Prop;
use App\Models\Publication;
use App\Models\PublicSiteSetting;
use App\Models\Scene;
use App\Models\Shot;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    private const RESTORABLE_FIELDS = [
        Project::class => ['title_ru', 'title_en', 'title_de', 'tagline', 'tagline_en', 'tagline_de', 'logline', 'logline_en', 'logline_de', 'synopsis', 'genre', 'duration', 'aspect_ratio', 'frame_rate', 'resolution', 'language', 'visual_principle', 'sound_principle', 'color_palette', 'camera_rules', 'production_stage', 'firefly_board_url'],
        Act::class => ['number', 'title', 'description', 'planned_duration_seconds', 'actual_duration_seconds', 'sort_order', 'status'],
        Scene::class => ['code', 'number', 'title', 'action_description', 'location_id', 'assignee_id', 'time_of_day', 'weather', 'costume', 'dialogue', 'sounds', 'planned_duration_seconds', 'actual_duration_seconds', 'status', 'notes'],
        Shot::class => ['code', 'number', 'description', 'hero_action', 'dog_action', 'shot_size', 'camera_position', 'camera_movement', 'lens', 'start_frame', 'end_frame', 'planned_duration_seconds', 'actual_duration_seconds', 'sound', 'dialogue', 'creation_method', 'ai_model', 'prompt', 'negative_prompt', 'credits_spent', 'is_selected', 'status', 'notes'],
        Character::class => ['name', 'role', 'description', 'age', 'appearance', 'build', 'clothing', 'voice', 'movement', 'continuity_details', 'forbidden_changes', 'status'],
        Location::class => ['name', 'description', 'time_of_day', 'weather', 'lighting', 'color_palette', 'continuity_elements', 'sounds', 'status'],
        Prop::class => ['name', 'type', 'description', 'color', 'dimensions', 'condition', 'continuity_requirements', 'status'],
        Asset::class => ['title', 'description', 'status', 'review_comment', 'author', 'source', 'has_usage_permission', 'comment'],
        ChecklistItem::class => ['title', 'description', 'comment', 'assignee_id', 'due_date', 'status'],
        Publication::class => ['title', 'title_en', 'title_de', 'description', 'description_en', 'description_de', 'type', 'status', 'published_at', 'unpublished_at', 'sort_order', 'is_published'],
        PublicSiteSetting::class => ['public_summary', 'public_summary_en', 'public_summary_de', 'poster_asset_id', 'contact', 'official_links', 'impressum', 'privacy_policy'],
        DonationSetting::class => ['title', 'title_en', 'title_de', 'goal_description', 'goal_description_en', 'goal_description_de', 'bank_details', 'payment_url', 'additional_methods', 'additional_methods_en', 'additional_methods_de', 'contact', 'image_asset_id', 'qr_asset_id', 'is_visible'],
    ];

    public function index(Request $request): View
    {
        $logs = ActivityLog::with('user')->latest()
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->paginate(50)->withQueryString();

        return view('activity.index', ['logs' => $logs, 'restorableTypes' => array_keys(self::RESTORABLE_FIELDS), 'backup' => $this->latestBackup()]);
    }

    public function restore(ActivityLog $activityLog): RedirectResponse
    {
        $subject = $activityLog->subject;
        abort_unless($subject && $activityLog->old_values, 422, 'Для этой записи нет данных восстановления.');

        $allowed = self::RESTORABLE_FIELDS[$subject::class] ?? null;
        abort_unless($allowed, 422, 'Этот тип записи нельзя восстановить автоматически.');

        $values = array_intersect_key($activityLog->old_values, array_flip($allowed));
        abort_if($values === [], 422, 'В этой записи нет восстанавливаемых полей.');

        $current = $subject->only(array_keys($values));
        $subject->update($values);
        ActivityLogger::write('Восстановление предыдущей версии', $subject, "По записи журнала №{$activityLog->id}", $current, $values);

        return back()->with('success', 'Предыдущие значения восстановлены. При необходимости это восстановление тоже можно отменить через журнал.');
    }

    private function latestBackup(): ?array
    {
        try {
            $root = rtrim((string) config('backup.path'), '/\\');
            if (! File::isDirectory($root)) {
                return null;
            }
            $directory = collect(File::directories($root))
                ->reject(fn (string $path) => str_ends_with($path, '.partial'))
                ->sortByDesc(fn (string $path) => basename($path))->first();
            $manifestPath = $directory ? $directory.DIRECTORY_SEPARATOR.'manifest.json' : null;

            return $manifestPath && File::exists($manifestPath)
                ? json_decode(File::get($manifestPath), true, flags: JSON_THROW_ON_ERROR)
                : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
