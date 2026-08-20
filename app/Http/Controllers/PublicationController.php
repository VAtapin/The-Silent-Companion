<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\DonationSetting;
use App\Models\Project;
use App\Models\Publication;
use App\Models\PublicSiteSetting;
use App\Services\ActivityLogger;
use App\Services\AssetStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicationController extends Controller
{
    public function __construct(private readonly AssetStorageService $storage) {}

    public function index(): View
    {
        $project = Project::firstOrFail();

        return view('publications.index', ['publications' => Publication::with(['assets', 'author'])->latest()->get(), 'assets' => Asset::whereNotNull('file_path')->latest()->get(), 'siteSettings' => PublicSiteSetting::firstOrCreate(['project_id' => $project->id]), 'donation' => DonationSetting::firstOrCreate(['project_id' => $project->id])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['author_id'] = $request->user()->id;
        $data['is_published'] = $request->boolean('is_published');
        if ($data['is_published'] && blank($data['published_at'] ?? null)) {
            $data['published_at'] = now();
        }
        $publication = Publication::create(collect($data)->except('asset_ids')->all());
        $publication->assets()->sync($data['asset_ids'] ?? []);
        ActivityLogger::write('Создание публикации', $publication, $publication->title);

        return back()->with('success', 'Публикация создана.');
    }

    public function update(Request $request, Publication $publication): RedirectResponse
    {
        $data = $this->validated($request);
        $data['is_published'] = $request->boolean('is_published');
        if ($data['is_published'] && blank($data['published_at'] ?? null)) {
            $data['published_at'] = now();
        }
        if (! $data['is_published'] && $publication->is_published) {
            $data['unpublished_at'] = now();
        } elseif ($data['is_published']) {
            $data['unpublished_at'] = null;
        }
        $changes = collect($data)->except('asset_ids')->all();
        $old = $publication->only(array_keys($changes));
        $publication->update($changes);
        $publication->assets()->sync($data['asset_ids'] ?? []);
        ActivityLogger::write('Изменение публикации', $publication, $publication->title, $old, $changes);

        return back()->with('success', 'Публикация обновлена.');
    }

    public function updateSite(Request $request): RedirectResponse
    {
        $project = Project::firstOrFail();
        $max = min(config('production.max_upload_kb'), 25600);
        $data = $request->validate([
            'public_summary' => ['nullable', 'string'],
            'poster_asset_id' => ['nullable', 'exists:assets,id'],
            'poster_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', "max:$max"],
            'contact' => ['nullable', 'string', 'max:255'],
            'official_links_text' => ['nullable', 'string'],
        ]);
        $posterId = $data['poster_asset_id'] ?? null;
        if ($request->hasFile('poster_file')) {
            $poster = Asset::create(array_merge($this->storage->store($request->file('poster_file')), [
                'uploaded_by' => $request->user()->id,
                'title' => 'Афиша фильма «Тихий спутник»',
                'description' => 'Основная афиша публичной страницы фильма.',
                'type' => 'Фото',
                'status' => 'Утверждено',
                'has_usage_permission' => true,
                'source' => 'Загружено через оформление публичной страницы',
            ]));
            $posterId = $poster->id;
            ActivityLogger::write('Загрузка афиши', $poster, $poster->title);
        }
        $links = collect(preg_split('/\r\n|\r|\n/', $data['official_links_text'] ?? ''))->map(fn ($line) => trim($line))->filter(function ($url) {
            $scheme = parse_url($url, PHP_URL_SCHEME);

            return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL);
        })->map(fn ($url) => ['url' => $url])->values()->all();
        $settings = PublicSiteSetting::firstOrNew(['project_id' => $project->id]);
        $changes = ['public_summary' => $data['public_summary'] ?? null, 'poster_asset_id' => $posterId, 'contact' => $data['contact'] ?? null, 'official_links' => $links];
        $old = $settings->exists ? $settings->only(array_keys($changes)) : [];
        $settings->fill($changes)->save();
        ActivityLogger::write('Изменение публичной страницы', $settings, null, $old, $changes);

        return back()->with('success', 'Публичная страница обновлена.');
    }

    public function updateDonation(Request $request): RedirectResponse
    {
        $project = Project::firstOrFail();
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'goal_description' => ['nullable', 'string'], 'bank_details' => ['nullable', 'string'], 'payment_url' => ['nullable', 'url'], 'additional_methods' => ['nullable', 'string'], 'contact' => ['nullable', 'string'], 'image_asset_id' => ['nullable', 'exists:assets,id'], 'qr_asset_id' => ['nullable', 'exists:assets,id'], 'is_visible' => ['nullable', 'boolean']]);
        $data['is_visible'] = $request->boolean('is_visible');
        $settings = DonationSetting::firstOrNew(['project_id' => $project->id]);
        $old = $settings->exists ? $settings->only(array_keys($data)) : [];
        $settings->fill($data)->save();
        ActivityLogger::write('Изменение настроек пожертвований', $settings, null, $old, $data);

        return back()->with('success', 'Настройки поддержки обновлены.');
    }

    private function validated(Request $request): array
    {
        return $request->validate(['title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'type' => ['required', 'string', 'max:100'], 'published_at' => ['nullable', 'date'], 'sort_order' => ['nullable', 'integer', 'min:0'], 'status' => ['required', Rule::in(Publication::STATUSES)], 'is_published' => ['nullable', 'boolean'], 'asset_ids' => ['array'], 'asset_ids.*' => ['exists:assets,id']]);
    }
}
