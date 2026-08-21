<?php

namespace App\Http\Controllers;

use App\Exceptions\OpenAiException;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiRequest;
use App\Models\AiUsageRecord;
use App\Models\Asset;
use App\Models\DonationSetting;
use App\Models\Project;
use App\Models\Publication;
use App\Models\PublicSiteSetting;
use App\Services\ActivityLogger;
use App\Services\AssetStorageService;
use App\Services\OpenAiBudgetService;
use App\Services\OpenAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicationController extends Controller
{
    public function __construct(
        private readonly AssetStorageService $storage,
        private readonly OpenAiService $openai,
        private readonly OpenAiBudgetService $budget,
    ) {}

    public function index(): View
    {
        $project = Project::firstOrFail();

        $serverUploadMaxBytes = min(UploadedFile::getMaxFilesize(), config('production.max_upload_kb') * 1024);
        $posterUploadMaxBytes = min(25 * 1024 * 1024, $serverUploadMaxBytes);

        return view('publications.index', ['publications' => Publication::with(['assets', 'author'])->latest()->get(), 'assets' => Asset::select(['id', 'title', 'type', 'status', 'mime_type', 'file_path', 'thumbnail_path', 'external_url'])->where(fn ($query) => $query->whereNotNull('file_path')->orWhereNotNull('external_url'))->latest()->get(), 'siteSettings' => PublicSiteSetting::firstOrCreate(['project_id' => $project->id]), 'donation' => DonationSetting::firstOrCreate(['project_id' => $project->id]), 'posterUploadMaxBytes' => $posterUploadMaxBytes, 'serverUploadMaxBytes' => $serverUploadMaxBytes]);
    }

    public function mediaOptions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', Rule::in(Asset::TYPES)],
            'status' => ['nullable', Rule::in(Asset::STATUSES)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $assets = Asset::query()
            ->where(fn ($query) => $query->whereNotNull('file_path')->orWhereNotNull('external_url'))
            ->when(filled($data['search'] ?? null), fn ($query) => $query->where('title', 'like', '%'.$data['search'].'%'))
            ->when(filled($data['type'] ?? null), fn ($query) => $query->where('type', $data['type']))
            ->when(filled($data['status'] ?? null), fn ($query) => $query->where('status', $data['status']))
            ->latest()
            ->paginate(24);

        return response()->json([
            'data' => $assets->getCollection()->map(fn (Asset $asset) => $this->pickerAsset($asset))->values(),
            'page' => $assets->currentPage(),
            'last_page' => $assets->lastPage(),
            'total' => $assets->total(),
        ]);
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

    public function translate(Request $request, Publication $publication): RedirectResponse
    {
        try {
            $this->budget->assertCanRequest($request->user());
        } catch (OpenAiException $exception) {
            return back()->withErrors(['openai' => $exception->getMessage()]);
        }

        $source = ['title' => $publication->title, 'description' => $publication->description];
        $prompt = "Переведи публикацию фильма с русского языка на английский и немецкий. Сохрани смысл, имена, абзацы и спокойный кинематографический тон. Ничего не добавляй от себя. Верни только корректный JSON без Markdown в формате: {\"en\":{\"title\":\"...\",\"description\":\"...\"},\"de\":{\"title\":\"...\",\"description\":\"...\"}}. Если описание пустое, верни пустую строку.\n\nИСХОДНЫЕ ДАННЫЕ:\n".json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $conversation = AiConversation::create(['project_id' => Project::firstOrFail()->id, 'user_id' => $request->user()->id, 'title' => 'Перевод публикации: '.$publication->title]);
        $aiRequest = AiRequest::create(['conversation_id' => $conversation->id, 'user_id' => $request->user()->id, 'subject_type' => $publication->getMorphClass(), 'subject_id' => $publication->id, 'request_type' => 'Текст', 'action' => 'translate_publication', 'model' => config('openai.text_model') ?: 'не настроена', 'source_text' => $publication->description, 'prompt' => $prompt, 'status' => 'Выполняется']);
        AiMessage::create(['conversation_id' => $conversation->id, 'request_id' => $aiRequest->id, 'user_id' => $request->user()->id, 'role' => 'user', 'content' => $prompt]);

        try {
            $result = $this->openai->text('Вы — профессиональный переводчик материалов фильма «Тихий спутник». Возвращайте исключительно запрошенный JSON.', $prompt);
            $translations = $this->translationPayload($result['text']);
            $changes = ['title_en' => $translations['en']['title'], 'description_en' => $translations['en']['description'], 'title_de' => $translations['de']['title'], 'description_de' => $translations['de']['description']];
            $old = $publication->only(array_keys($changes));

            DB::transaction(function () use ($request, $publication, $conversation, $aiRequest, $result, $changes, $old): void {
                $publication->update($changes);
                $aiRequest->update(['model' => $result['model'], 'result_text' => $result['text'], 'status' => 'Завершён', 'decision' => 'apply', 'api_request_id' => $result['request_id'], 'input_tokens' => $result['input_tokens'], 'output_tokens' => $result['output_tokens'], 'cost' => $result['cost'], 'duration_ms' => $result['duration_ms']]);
                AiMessage::create(['conversation_id' => $conversation->id, 'request_id' => $aiRequest->id, 'role' => 'assistant', 'content' => $result['text']]);
                AiUsageRecord::create(['request_id' => $aiRequest->id, 'user_id' => $request->user()->id, 'model' => $result['model'], 'usage_type' => 'Текст', 'input_tokens' => $result['input_tokens'], 'output_tokens' => $result['output_tokens'], 'cost' => $result['cost'], 'usage_date' => today()]);
                ActivityLogger::write('Перевод публикации с ИИ', $publication, $publication->title, $old, $changes);
            });
        } catch (OpenAiException $exception) {
            $aiRequest->update(['status' => 'Ошибка', 'error_message' => $exception->getMessage()]);

            return back()->withErrors(['openai' => $exception->getMessage()]);
        }

        return back()->with('success', 'Английский и немецкий переводы публикации готовы.');
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

    public function updateLegal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'impressum' => ['required', 'string', 'max:100000'],
            'privacy_policy' => ['required', 'string', 'max:100000'],
        ]);
        $settings = PublicSiteSetting::firstOrNew(['project_id' => Project::firstOrFail()->id]);
        $old = $settings->exists ? $settings->only(array_keys($data)) : [];
        $settings->fill($data)->save();
        ActivityLogger::write('Изменение правовых страниц', $settings, 'Impressum и Datenschutz', $old, $data);

        return redirect()->route('publications.index', ['tab' => 'legal'])->with('success', 'Правовые страницы обновлены.');
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

    private function translationPayload(string $text): array
    {
        $candidate = trim($text);
        if (preg_match('/\{.*\}/s', $candidate, $match)) {
            $candidate = $match[0];
        }
        $payload = json_decode($candidate, true);
        foreach (['en', 'de'] as $locale) {
            if (! is_array($payload[$locale] ?? null) || ! is_string($payload[$locale]['title'] ?? null) || ! is_string($payload[$locale]['description'] ?? null)) {
                throw new OpenAiException('OpenAI вернул перевод в неверном формате. Повторите запрос.');
            }
            $payload[$locale]['title'] = $this->normalizeAiLineBreaks($payload[$locale]['title']);
            $payload[$locale]['description'] = $this->normalizeAiLineBreaks($payload[$locale]['description']);
        }

        return $payload;
    }

    private function normalizeAiLineBreaks(string $value): string
    {
        $value = str_replace(['\\\\r\\\\n', '\\\\n', '\\\\r', '\\r\\n', '\\n', '\\r'], "\n", $value);

        return str_replace(["\r\n", "\r"], "\n", $value);
    }

    private function pickerAsset(Asset $asset): array
    {
        $youtubeId = $asset->youtubeId();
        $isImage = str_starts_with((string) $asset->mime_type, 'image/');
        $isVideo = str_starts_with((string) $asset->mime_type, 'video/');

        return [
            'id' => $asset->id,
            'title' => $asset->title,
            'type' => $youtubeId ? 'YouTube' : $asset->type,
            'status' => $asset->status,
            'kind' => $youtubeId ? 'youtube' : ($isImage ? 'image' : ($isVideo ? 'video' : 'other')),
            'thumbnail' => $youtubeId ? "https://i.ytimg.com/vi/{$youtubeId}/hqdefault.jpg" : ($isImage ? route('assets.thumbnail', $asset) : null),
        ];
    }
}
