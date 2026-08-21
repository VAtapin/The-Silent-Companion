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
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicationController extends Controller
{
    public function __construct(private readonly AssetStorageService $storage) {}

    public function index(): View
    {
        $project = Project::firstOrFail();

        $serverUploadMaxBytes = min(UploadedFile::getMaxFilesize(), config('production.max_upload_kb') * 1024);
        $posterUploadMaxBytes = min(25 * 1024 * 1024, $serverUploadMaxBytes);

        return view('publications.index', ['publications' => Publication::with(['assets', 'author'])->latest()->get(), 'assets' => Asset::where(fn ($query) => $query->whereNotNull('file_path')->orWhereNotNull('external_url'))->latest()->get(), 'siteSettings' => PublicSiteSetting::firstOrCreate(['project_id' => $project->id]), 'donation' => DonationSetting::firstOrCreate(['project_id' => $project->id]), 'posterUploadMaxBytes' => $posterUploadMaxBytes, 'serverUploadMaxBytes' => $serverUploadMaxBytes]);
    }

    public function store(Request $request): RedirectResponse
    {
        $publish = $request->input('publish_action') === 'publish';
        $request->merge(['status' => $publish ? 'Опубликовано' : 'Черновик', 'is_published' => $publish]);
        $data = $this->validated($request);
        $data['author_id'] = $request->user()->id;
        $data['is_published'] = $request->boolean('is_published');
        if ($data['is_published'] && blank($data['published_at'] ?? null)) {
            $data['published_at'] = now();
        }
        $assetIds = $this->mediaAssetIds($request, $data);
        $publication = Publication::create(collect($data)->except(['asset_ids', 'media_file', 'youtube_url', 'publish_action'])->all());
        $publication->assets()->sync($assetIds);
        ActivityLogger::write('Создание публикации', $publication, $publication->title);

        return back()->with('success', 'Публикация создана.');
    }

    public function update(Request $request, Publication $publication): RedirectResponse
    {
        $data = $this->validated($request);
        $data['is_published'] = $request->has('is_published') ? $request->boolean('is_published') : $publication->is_published;
        if ($data['is_published'] && blank($data['published_at'] ?? null)) {
            $data['published_at'] = now();
        }
        if (! $data['is_published'] && $publication->is_published) {
            $data['unpublished_at'] = now();
        } elseif ($data['is_published']) {
            $data['unpublished_at'] = null;
        }
        $assetIds = $this->mediaAssetIds($request, $data);
        $changes = collect($data)->except(['asset_ids', 'media_file', 'youtube_url', 'publish_action'])->all();
        $old = $publication->only(array_keys($changes));
        $publication->update($changes);
        $publication->assets()->sync($assetIds);
        ActivityLogger::write('Изменение публикации', $publication, $publication->title, $old, $changes);

        return back()->with('success', 'Публикация обновлена.');
    }

    public function visibility(Request $request, Publication $publication): RedirectResponse
    {
        $data = $request->validate(['visible' => ['required', 'boolean']]);
        $visible = (bool) $data['visible'];
        $old = $publication->only(['status', 'is_published', 'published_at', 'unpublished_at']);
        $changes = $visible
            ? ['status' => 'Опубликовано', 'is_published' => true, 'published_at' => $publication->published_at ?: now(), 'unpublished_at' => null]
            : ['status' => 'Скрыто', 'is_published' => false, 'unpublished_at' => now()];
        $publication->update($changes);
        ActivityLogger::write($visible ? 'Публикация показана на сайте' : 'Публикация скрыта с сайта', $publication, $publication->title, $old, $changes);

        return back()->with('success', $visible ? 'Публикация появилась на главной странице.' : 'Публикация скрыта с главной страницы.');
    }

    public function updateSite(Request $request): RedirectResponse
    {
        $project = Project::firstOrFail();
        $data = $request->validate([
            'public_summary' => ['nullable', 'string'],
            'public_summary_en' => ['nullable', 'string'],
            'public_summary_de' => ['nullable', 'string'],
            'poster_asset_id' => ['nullable', 'exists:assets,id'],
            'contact' => ['nullable', 'string', 'max:255'],
            'official_links_text' => ['nullable', 'string'],
        ]);
        $posterId = $data['poster_asset_id'] ?? null;
        $links = collect(preg_split('/\r\n|\r|\n/', $data['official_links_text'] ?? ''))->map(fn ($line) => trim($line))->filter(function ($url) {
            $scheme = parse_url($url, PHP_URL_SCHEME);

            return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL);
        })->map(fn ($url) => ['url' => $url])->values()->all();
        $settings = PublicSiteSetting::firstOrNew(['project_id' => $project->id]);
        $changes = ['public_summary' => $data['public_summary'] ?? null, 'public_summary_en' => $data['public_summary_en'] ?? null, 'public_summary_de' => $data['public_summary_de'] ?? null, 'poster_asset_id' => $posterId, 'contact' => $data['contact'] ?? null, 'official_links' => $links];
        $old = $settings->exists ? $settings->only(array_keys($changes)) : [];
        $settings->fill($changes)->save();
        ActivityLogger::write('Изменение публичной страницы', $settings, null, $old, $changes);

        return back()->with('success', 'Публичная страница обновлена.');
    }

    public function uploadPoster(Request $request): RedirectResponse
    {
        $maxKb = max(1, (int) floor(min(25 * 1024 * 1024, UploadedFile::getMaxFilesize(), config('production.max_upload_kb') * 1024) / 1024));
        $data = $request->validate([
            'poster_file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', "max:$maxKb"],
        ], [
            'poster_file.required' => 'Выберите файл афиши.',
            'poster_file.uploaded' => 'Сервер отклонил файл из-за ограничения PHP. Уменьшите файл или увеличьте upload_max_filesize и post_max_size в настройках PHP 8.4.',
            'poster_file.image' => 'Афиша должна быть изображением.',
            'poster_file.mimes' => 'Допустимы только JPG, PNG и WEBP.',
            'poster_file.max' => 'Файл больше фактического лимита сервера.',
        ]);

        $project = Project::firstOrFail();
        $poster = Asset::create(array_merge($this->storage->store($data['poster_file']), [
            'uploaded_by' => $request->user()->id,
            'title' => 'Афиша фильма «Тихий спутник»',
            'description' => 'Основная афиша публичной страницы фильма.',
            'type' => 'Фото',
            'status' => 'Утверждено',
            'has_usage_permission' => true,
            'source' => 'Загружено через оформление публичной страницы',
        ]));
        $settings = PublicSiteSetting::firstOrCreate(['project_id' => $project->id]);
        $old = ['poster_asset_id' => $settings->poster_asset_id];
        $settings->update(['poster_asset_id' => $poster->id]);
        ActivityLogger::write('Загрузка и установка афиши', $settings, $poster->title, $old, ['poster_asset_id' => $poster->id]);

        return redirect()->route('publications.index', ['tab' => 'site'])->with('success', 'Афиша загружена и установлена на главной странице.');
    }

    public function updateDonation(Request $request): RedirectResponse
    {
        $project = Project::firstOrFail();
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'title_en' => ['nullable', 'string', 'max:255'], 'title_de' => ['nullable', 'string', 'max:255'], 'goal_description' => ['nullable', 'string'], 'goal_description_en' => ['nullable', 'string'], 'goal_description_de' => ['nullable', 'string'], 'bank_details' => ['nullable', 'string'], 'payment_url' => ['nullable', 'url'], 'additional_methods' => ['nullable', 'string'], 'additional_methods_en' => ['nullable', 'string'], 'additional_methods_de' => ['nullable', 'string'], 'contact' => ['nullable', 'string'], 'image_asset_id' => ['nullable', 'exists:assets,id'], 'qr_asset_id' => ['nullable', 'exists:assets,id'], 'is_visible' => ['nullable', 'boolean']]);
        $data['is_visible'] = $request->boolean('is_visible');
        $settings = DonationSetting::firstOrNew(['project_id' => $project->id]);
        $old = $settings->exists ? $settings->only(array_keys($data)) : [];
        $settings->fill($data)->save();
        ActivityLogger::write('Изменение настроек пожертвований', $settings, null, $old, $data);

        return back()->with('success', 'Настройки поддержки обновлены.');
    }

    private function validated(Request $request): array
    {
        $maxKb = max(1, (int) floor(min(UploadedFile::getMaxFilesize(), config('production.max_upload_kb') * 1024) / 1024));

        return $request->validate([
            'title' => ['required', 'string', 'max:255'], 'title_en' => ['nullable', 'string', 'max:255'], 'title_de' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'], 'description_en' => ['nullable', 'string'], 'description_de' => ['nullable', 'string'], 'type' => ['required', 'string', 'max:100'],
            'published_at' => ['nullable', 'date'], 'sort_order' => ['nullable', 'integer', 'min:0'], 'status' => ['required', Rule::in(Publication::STATUSES)],
            'is_published' => ['nullable', 'boolean'], 'publish_action' => ['nullable', Rule::in(['publish', 'draft'])],
            'asset_ids' => ['array'], 'asset_ids.*' => ['exists:assets,id'],
            'media_file' => ['nullable', 'file', "max:$maxKb", 'mimes:jpg,jpeg,png,webp,mp4,mov,webm'],
            'youtube_url' => ['nullable', 'url', 'max:2000', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value && ! Asset::youtubeIdFromUrl((string) $value)) {
                    $fail('Укажите корректную ссылку YouTube.');
                }
            }],
        ], [
            'media_file.uploaded' => 'Сервер отклонил файл из-за ограничения upload_max_filesize или post_max_size в PHP 8.4.',
            'media_file.max' => 'Файл превышает фактический лимит загрузки сервера.',
            'media_file.mimes' => 'Можно загрузить JPG, PNG, WEBP, MP4, MOV или WEBM.',
        ]);
    }

    private function mediaAssetIds(Request $request, array $data): array
    {
        $ids = collect($data['asset_ids'] ?? []);
        if ($request->hasFile('media_file')) {
            $file = $request->file('media_file');
            $type = str_starts_with((string) $file->getMimeType(), 'video/') ? 'Видео' : 'Фото';
            $asset = Asset::create(array_merge($this->storage->store($file), [
                'uploaded_by' => $request->user()->id, 'title' => $data['title'], 'description' => $data['description'] ?? null,
                'type' => $type, 'status' => 'Утверждено', 'has_usage_permission' => true, 'source' => 'Загружено при создании публикации',
            ]));
            $ids->push($asset->id);
        }
        if (filled($data['youtube_url'] ?? null)) {
            $youtubeId = Asset::youtubeIdFromUrl($data['youtube_url']);
            $url = "https://www.youtube.com/watch?v={$youtubeId}";
            $asset = Asset::firstOrCreate(['external_url' => $url], [
                'uploaded_by' => $request->user()->id, 'title' => $data['title'], 'description' => $data['description'] ?? null,
                'type' => 'Видео', 'status' => 'Утверждено', 'mime_type' => 'video/youtube', 'has_usage_permission' => true, 'source' => 'YouTube',
            ]);
            $ids->push($asset->id);
        }

        return $ids->filter()->unique()->values()->all();
    }
}
