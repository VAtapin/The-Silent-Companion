<?php

namespace App\Http\Controllers;

use App\Models\AssetCategory;
use App\Models\ChecklistItem;
use App\Models\ChecklistManualOverride;
use App\Models\ChecklistSection;
use App\Models\Project;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ChecklistProgressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChecklistController extends Controller
{
    public function __construct(private readonly ChecklistProgressService $progress) {}

    public function index(Request $request): View
    {
        $filter = $request->validate(['filter' => ['nullable', Rule::in(['done', 'warning'])]])['filter'] ?? null;
        $sections = ChecklistSection::with(['children.items.requirements.category', 'items.requirements.category'])->whereNull('parent_id')->orderBy('sort_order')->get();
        if ($filter) {
            $matches = fn (ChecklistItem $item): bool => $filter === 'done' ? $item->status === 'Выполнено' : (bool) $item->has_warning;
            $sections->each(function (ChecklistSection $section) use ($matches): void {
                $section->setRelation('items', $section->items->filter($matches)->values());
                $section->children->each(fn (ChecklistSection $child) => $child->setRelation('items', $child->items->filter($matches)->values()));
            });
        }

        return view('checklist.index', [
            'sections' => $sections,
            'allSections' => ChecklistSection::orderBy('title')->get(),
            'categories' => AssetCategory::orderBy('name')->get(),
            'users' => User::where('is_active', true)->orderBy('name')->get(),
            'filter' => $filter,
        ]);
    }

    public function storeSection(Request $request): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'parent_id' => ['nullable', 'exists:checklist_sections,id'], 'sort_order' => ['nullable', 'integer', 'min:0']]);
        Project::firstOrFail()->sections()->create($data);

        return back()->with('success', 'Раздел чек-листа создан.');
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'section_id' => ['required', 'exists:checklist_sections,id'], 'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'], 'is_required' => ['nullable', 'boolean'], 'assignee_id' => ['nullable', 'exists:users,id'],
            'due_date' => ['nullable', 'date'], 'completion_method' => ['required', 'string', 'max:80'], 'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
        $data['is_required'] = $request->boolean('is_required');
        $item = ChecklistItem::create($data);
        ActivityLogger::write('Создание пункта чек-листа', $item, $item->title);

        return back()->with('success', 'Пункт чек-листа создан.');
    }

    public function updateItem(Request $request, ChecklistItem $item): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'comment' => ['nullable', 'string'], 'assignee_id' => ['nullable', 'exists:users,id'], 'due_date' => ['nullable', 'date']]);
        $old = $item->only(array_keys($data));
        $item->update($data);
        ActivityLogger::write('Изменение пункта чек-листа', $item, $item->title, $old, $data);

        return back()->with('success', 'Пункт обновлён.');
    }

    public function storeRequirement(Request $request, ChecklistItem $item): RedirectResponse
    {
        $data = $request->validate([
            'asset_type' => ['required', 'string', 'max:80'], 'asset_category_id' => ['nullable', 'exists:asset_categories,id'],
            'label' => ['required', 'string', 'max:255'], 'minimum_count' => ['required', 'integer', 'min:0'],
            'recommended_count' => ['nullable', 'integer', 'min:0'], 'is_required' => ['nullable', 'boolean'], 'approved_only' => ['nullable', 'boolean'],
        ]);
        $data['is_required'] = $request->boolean('is_required');
        $data['approved_only'] = $request->boolean('approved_only');
        $item->requirements()->create($data);
        $this->progress->recalculate($item);

        return back()->with('success', 'Требование добавлено.');
    }

    public function manualComplete(Request $request, ChecklistItem $item): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:2000'], 'confirm_missing' => ['nullable', 'accepted']]);
        $this->progress->recalculate($item);
        $item->refresh();
        if ($item->status !== 'Выполнено' && ! $request->boolean('confirm_missing')) {
            return back()->withErrors(['manual' => 'Требования ещё не выполнены. Подтвердите ручное завершение, чтобы сохранить решение с предупреждением.']);
        }
        ChecklistManualOverride::where('checklist_item_id', $item->id)->where('is_active', true)->update(['is_active' => false]);
        ChecklistManualOverride::create(['checklist_item_id' => $item->id, 'user_id' => $request->user()->id, 'reason' => $data['reason'] ?? null, 'is_active' => true]);
        $this->progress->recalculate($item);
        ActivityLogger::write('Ручное выполнение чек-листа', $item, $data['reason'] ?? null);

        return back()->with('success', 'Ручное решение сохранено.');
    }

    public function recalculate(): RedirectResponse
    {
        ChecklistItem::query()->each(fn (ChecklistItem $item) => $this->progress->recalculate($item));

        return back()->with('success', 'Прогресс чек-листа пересчитан.');
    }
}
