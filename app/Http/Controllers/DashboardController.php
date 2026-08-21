<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AiRequest;
use App\Models\AiUsageRecord;
use App\Models\Asset;
use App\Models\ChecklistItem;
use App\Models\ChecklistSection;
use App\Models\Project;
use App\Models\Scene;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $project = Project::firstOrFail();
        $items = ChecklistItem::query()->with('requirements')->get();
        $completion = $items->isEmpty() ? 0 : (int) round($items->avg('progress'));
        $sections = ChecklistSection::query()->whereNull('parent_id')->with(['children.items', 'items'])->orderBy('sort_order')->get();

        return view('dashboard', [
            'project' => $project,
            'completion' => $completion,
            'sections' => $sections,
            'stats' => [
                'done' => $items->where('status', 'Выполнено')->count(),
                'open' => $items->where('status', '!=', 'Выполнено')->count(),
                'warning' => $items->where('has_warning', true)->count(),
            ],
            'missingItems' => ChecklistItem::where('status', 'Требуются материалы')->orWhere('has_warning', true)->limit(6)->get(),
            'recentAssets' => Asset::with('category')->latest()->limit(6)->get(),
            'needsAttentionAssets' => Asset::query()
                ->where('uploaded_by', auth()->id())
                ->whereIn('status', ['Отклонено', 'Требуется переснять'])
                ->latest('updated_at')->limit(6)->get(),
            'pendingAssets' => Asset::where('status', 'На проверке')->count(),
            'reviewScenes' => Scene::where('status', 'На проверке')->count(),
            'activity' => ActivityLog::latest()->limit(8)->get(),
            'aiUsage' => [
                'today' => (float) AiUsageRecord::whereDate('usage_date', today())->sum('cost'),
                'month' => (float) AiUsageRecord::whereBetween('usage_date', [now()->startOfMonth(), now()->endOfMonth()])->sum('cost'),
                'text' => AiUsageRecord::where('usage_type', 'Текст')->whereMonth('usage_date', now()->month)->count(),
                'images' => (int) AiUsageRecord::whereMonth('usage_date', now()->month)->sum('images'),
                'errors' => AiRequest::where('status', 'Ошибка')->whereMonth('created_at', now()->month)->count(),
                'budget' => (float) config('openai.monthly_budget'),
            ],
            'aiByUsers' => AiUsageRecord::query()
                ->join('users', 'users.id', '=', 'ai_usage_records.user_id')
                ->whereBetween('usage_date', [now()->startOfMonth(), now()->endOfMonth()])
                ->groupBy('users.id', 'users.name')->select('users.name', DB::raw('SUM(ai_usage_records.cost) as total_cost'))
                ->orderByDesc('total_cost')->limit(5)->get(),
            'aiByScenes' => AiUsageRecord::query()
                ->join('ai_requests', 'ai_requests.id', '=', 'ai_usage_records.request_id')
                ->join('scenes', 'scenes.id', '=', 'ai_requests.subject_id')
                ->where('ai_requests.subject_type', Scene::class)
                ->whereBetween('usage_date', [now()->startOfMonth(), now()->endOfMonth()])
                ->groupBy('scenes.id', 'scenes.code', 'scenes.title')->select('scenes.code', 'scenes.title', DB::raw('SUM(ai_usage_records.cost) as total_cost'))
                ->orderByDesc('total_cost')->limit(5)->get(),
        ]);
    }
}
