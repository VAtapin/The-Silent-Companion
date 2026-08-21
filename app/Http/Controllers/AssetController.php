<?php

namespace App\Http\Controllers;

use App\Models\Act;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Character;
use App\Models\ChecklistItem;
use App\Models\DonationSetting;
use App\Models\Location;
use App\Models\Prop;
use App\Models\PublicSiteSetting;
use App\Models\Scene;
use App\Models\Shot;
use App\Services\ActivityLogger;
use App\Services\AssetStorageService;
use App\Services\ChecklistProgressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetController extends Controller
{
    public function __construct(
        private readonly AssetStorageService $storage,
        private readonly ChecklistProgressService $progress,
    ) {}

    public function index(Request $request): View
    {
        $assets = Asset::with(['category', 'uploader'])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($inner) => $inner->where('title', 'like', '%'.$request->q.'%')->orWhere('description', 'like', '%'.$request->q.'%')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->category_id))
            ->latest()->paginate(18)->withQueryString();

        return view('assets.index', [
            'assets' => $assets,
            'categories' => AssetCategory::orderBy('name')->get(),
            'protectedAssetIds' => $this->siteProtectedAssetIds(),
        ]);
    }

    public function create(): View
    {
        return view('assets.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $max = config('production.max_upload_kb');
        $extensions = implode(',', config('production.allowed_mimes'));
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in(Asset::TYPES)], 'category_id' => ['nullable', 'exists:asset_categories,id'],
            'file' => ['nullable', 'file', "max:$max", "mimes:$extensions", 'required_without_all:external_url,text_content'],
            'external_url' => ['nullable', 'url', 'max:2000', 'required_without_all:file,text_content'],
            'text_content' => ['nullable', 'string', 'required_without_all:file,external_url'],
            'view_angle' => ['nullable', 'string', 'max:255'], 'captured_at' => ['nullable', 'date'], 'author' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:500'], 'has_usage_permission' => ['nullable', 'boolean'], 'comment' => ['nullable', 'string'],
            'status' => ['required', Rule::in(Asset::STATUSES)],
            'character_ids' => ['array'], 'character_ids.*' => ['exists:characters,id'], 'location_ids' => ['array'], 'location_ids.*' => ['exists:locations,id'],
            'prop_ids' => ['array'], 'prop_ids.*' => ['exists:props,id'], 'act_ids' => ['array'], 'act_ids.*' => ['exists:acts,id'],
            'scene_ids' => ['array'], 'scene_ids.*' => ['exists:scenes,id'], 'shot_ids' => ['array'], 'shot_ids.*' => ['exists:shots,id'],
            'checklist_item_ids' => ['array'], 'checklist_item_ids.*' => ['exists:checklist_items,id'],
        ]);
        $assetData = collect($data)->except(['file', 'character_ids', 'location_ids', 'prop_ids', 'act_ids', 'scene_ids', 'shot_ids', 'checklist_item_ids'])->all();
        $assetData['uploaded_by'] = $request->user()->id;
        $assetData['has_usage_permission'] = $request->boolean('has_usage_permission');
        if ($request->hasFile('file')) {
            $assetData += $this->storage->store($request->file('file'));
        }
        $asset = Asset::create($assetData);
        $this->syncLinks($asset, $data);
        $this->progress->recalculateForAsset($asset);
        ActivityLogger::write('Загрузка материала', $asset, $asset->title);

        return redirect()->route('assets.show', $asset)->with('success', 'Материал сохранён и учтён в чек-листе.');
    }

    public function show(Asset $asset): View
    {
        $asset->load(['category', 'uploader', 'characters', 'locations', 'props', 'acts', 'scenes', 'shots', 'checklistItems.requirements']);

        return view('assets.show', [
            'asset' => $asset,
            'deletionProtectionReason' => $this->deletionProtectionReason($asset),
        ]);
    }

    public function edit(Asset $asset): View
    {
        $asset->load(['characters', 'locations', 'props', 'acts', 'scenes', 'shots', 'checklistItems']);

        return view('assets.edit', ['asset' => $asset, ...$this->formOptions()]);
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in(Asset::TYPES)], 'category_id' => ['nullable', 'exists:asset_categories,id'],
            'external_url' => ['nullable', 'url', 'max:2000'], 'text_content' => ['nullable', 'string'],
            'view_angle' => ['nullable', 'string', 'max:255'], 'captured_at' => ['nullable', 'date'], 'author' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:500'], 'has_usage_permission' => ['nullable', 'boolean'], 'comment' => ['nullable', 'string'],
            'status' => ['required', Rule::in(Asset::STATUSES)],
            'character_ids' => ['array'], 'character_ids.*' => ['exists:characters,id'], 'location_ids' => ['array'], 'location_ids.*' => ['exists:locations,id'],
            'prop_ids' => ['array'], 'prop_ids.*' => ['exists:props,id'], 'act_ids' => ['array'], 'act_ids.*' => ['exists:acts,id'],
            'scene_ids' => ['array'], 'scene_ids.*' => ['exists:scenes,id'], 'shot_ids' => ['array'], 'shot_ids.*' => ['exists:shots,id'],
            'checklist_item_ids' => ['array'], 'checklist_item_ids.*' => ['exists:checklist_items,id'],
        ]);
        $changes = collect($data)->except(['character_ids', 'location_ids', 'prop_ids', 'act_ids', 'scene_ids', 'shot_ids', 'checklist_item_ids'])->all();
        $changes['has_usage_permission'] = $request->boolean('has_usage_permission');
        $old = $asset->only(array_keys($changes));
        $asset->update($changes);
        $this->syncLinks($asset, $data);
        $this->progress->recalculateForAsset($asset);
        ActivityLogger::write('Редактирование материала', $asset, $asset->title, $old, $changes);

        return redirect()->route('assets.show', $asset)->with('success', 'Материал обновлён, связи и чек-лист пересчитаны.');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        if ($reason = $this->deletionProtectionReason($asset)) {
            return back()->withErrors(['asset' => $reason]);
        }

        $items = $asset->checklistItems()->get();
        $title = $asset->title;
        ActivityLogger::write('Удаление материала', $asset, $title, $asset->toArray(), []);
        $asset->delete();
        $items->each(fn (ChecklistItem $item) => $this->progress->recalculate($item));

        return redirect()->route('assets.index')->with('success', "Материал «{$title}» удалён из рабочей зоны. Исходный файл сохранён от случайного уничтожения.");
    }

    public function updateStatus(Request $request, Asset $asset): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Asset::STATUSES)],
            'review_comment' => [Rule::requiredIf(in_array($request->status, ['Отклонено', 'Требуется переснять'], true)), 'nullable', 'string', 'max:4000'],
        ], [
            'review_comment.required' => 'Напишите, что именно нужно исправить или переснять. Этот комментарий увидит загрузивший материал человек.',
        ]);
        $old = $asset->only(['status', 'review_comment']);
        $asset->update($data);
        $this->progress->recalculateForAsset($asset);
        ActivityLogger::write('Изменение статуса материала', $asset, $data['review_comment'] ?? null, $old, $data);

        return back()->with('success', 'Статус обновлён, чек-лист пересчитан.');
    }

    public function download(Asset $asset, bool $thumbnail = false): StreamedResponse|BinaryFileResponse
    {
        $path = $thumbnail ? $asset->thumbnail_path : $asset->file_path;
        abort_unless($path && Storage::disk($asset->disk)->exists($path), 404);

        return Storage::disk($asset->disk)->download($path, $thumbnail ? 'preview.jpg' : $asset->original_name);
    }

    public function preview(Asset $asset): StreamedResponse|BinaryFileResponse
    {
        abort_unless($asset->file_path && Storage::disk($asset->disk)->exists($asset->file_path), 404);

        if ($asset->disk === 'local') {
            return response()->file(Storage::disk($asset->disk)->path($asset->file_path), [
                'Content-Type' => $asset->mime_type ?: 'application/octet-stream',
                'Content-Disposition' => 'inline',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        return Storage::disk($asset->disk)->response($asset->file_path, $asset->original_name, ['Cache-Control' => 'private, no-store']);
    }

    public function thumbnail(Asset $asset): StreamedResponse
    {
        $path = $asset->thumbnail_path;
        if ((! $path || ! Storage::disk($asset->disk)->exists($path)) && $asset->file_path && Storage::disk($asset->disk)->exists($asset->file_path)) {
            $path = $this->storage->createThumbnail($asset->disk, $asset->file_path, $asset->mime_type);
            if ($path) {
                $asset->updateQuietly(['thumbnail_path' => $path]);
            }
        }
        if (! $path || ! Storage::disk($asset->disk)->exists($path)) {
            $path = $asset->file_path;
        }
        abort_unless($path && Storage::disk($asset->disk)->exists($path), 404);

        return Storage::disk($asset->disk)->response($path, 'preview.jpg', ['Cache-Control' => 'private, max-age=86400']);
    }

    private function syncLinks(Asset $asset, array $data): void
    {
        $map = ['characters' => 'character_ids', 'locations' => 'location_ids', 'props' => 'prop_ids', 'acts' => 'act_ids', 'scenes' => 'scene_ids', 'shots' => 'shot_ids', 'checklistItems' => 'checklist_item_ids'];
        foreach ($map as $relation => $field) {
            $asset->{$relation}()->sync($data[$field] ?? []);
        }
    }

    private function formOptions(): array
    {
        return [
            'categories' => AssetCategory::orderBy('scope')->orderBy('name')->get(), 'characters' => Character::orderBy('name')->get(),
            'locations' => Location::orderBy('name')->get(), 'props' => Prop::orderBy('name')->get(), 'acts' => Act::orderBy('number')->get(),
            'scenes' => Scene::orderBy('code')->get(), 'shots' => Shot::orderBy('code')->get(), 'checklistItems' => ChecklistItem::orderBy('title')->get(),
        ];
    }

    private function deletionProtectionReason(Asset $asset): ?string
    {
        if (in_array($asset->status, ['Утверждено', 'Используется в фильме', 'Финальная версия'], true)) {
            return "Материал со статусом «{$asset->status}» защищён от удаления. Сначала измените его статус.";
        }
        if ($this->siteProtectedAssetIds()->contains($asset->id)) {
            return 'Материал используется на публичном сайте. Сначала уберите его с сайта или замените другим материалом.';
        }

        return null;
    }

    private function siteProtectedAssetIds(): Collection
    {
        $publicationAssetIds = DB::table('publication_assets')
            ->join('publications', 'publications.id', '=', 'publication_assets.publication_id')
            ->where('publications.is_published', true)
            ->where('publications.status', 'Опубликовано')
            ->whereNull('publications.unpublished_at')
            ->pluck('publication_assets.asset_id');

        return $publicationAssetIds
            ->merge(PublicSiteSetting::pluck('poster_asset_id'))
            ->merge(DonationSetting::pluck('image_asset_id'))
            ->merge(DonationSetting::pluck('qr_asset_id'))
            ->filter()->map(fn ($id) => (int) $id)->unique()->values();
    }
}
