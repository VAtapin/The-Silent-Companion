<?php

namespace App\Http\Controllers;

use App\Exceptions\OpenAiException;
use App\Jobs\GenerateAiImages;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiRequest;
use App\Models\AiUsageRecord;
use App\Models\Asset;
use App\Models\Character;
use App\Models\ChecklistItem;
use App\Models\Document;
use App\Models\Location;
use App\Models\Project;
use App\Models\ProjectVersion;
use App\Models\Scene;
use App\Models\Shot;
use App\Services\AiContextBuilder;
use App\Services\AssetStorageService;
use App\Services\OpenAiBudgetService;
use App\Services\OpenAiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AiAssistantController extends Controller
{
    public const ACTIONS = ['proofread' => 'Исправить ошибки', 'improve' => 'Улучшить формулировку', 'shorten' => 'Сократить', 'expand' => 'Расширить', 'cinematic' => 'Сделать кинематографичнее', 'preserve_style' => 'Сохранить авторский стиль', 'screenplay' => 'Переписать как сценарную сцену', 'director' => 'Переписать как режиссёрское описание', 'technical' => 'Переписать как техническое задание', 'summary' => 'Подготовить краткое содержание', 'logline' => 'Создать логлайн', 'synopsis' => 'Создать синопсис', 'contradictions' => 'Проверить противоречия', 'compare' => 'Сравнить две версии', 'variants' => 'Предложить варианты', 'translate_ru' => 'Перевести на русский', 'translate_en' => 'Перевести на английский', 'translate_de' => 'Перевести на немецкий'];

    public function __construct(private readonly OpenAiService $openai, private readonly OpenAiBudgetService $budget, private readonly AiContextBuilder $context, private readonly AssetStorageService $assetStorage) {}

    public function index(): View
    {
        $month = [now()->startOfMonth(), now()->endOfMonth()];

        return view('ai.index', ['actions' => self::ACTIONS, 'documents' => Document::orderBy('title')->get(), 'scenes' => Scene::orderBy('code')->get(), 'shots' => Shot::orderBy('code')->get(), 'assets' => Asset::latest()->limit(100)->get(), 'characters' => Character::orderBy('name')->get(), 'locations' => Location::orderBy('name')->get(), 'checklistItems' => ChecklistItem::orderBy('title')->get(), 'requests' => AiRequest::where('user_id', auth()->id())->with('generatedAssets.asset')->latest()->limit(30)->get(), 'usage' => ['today' => (float) AiUsageRecord::whereDate('usage_date', today())->sum('cost'), 'month' => (float) AiUsageRecord::whereBetween('usage_date', $month)->sum('cost'), 'text' => AiUsageRecord::where('usage_type', 'Текст')->whereBetween('usage_date', $month)->count(), 'images' => (int) AiUsageRecord::whereBetween('usage_date', $month)->sum('images'), 'errors' => AiRequest::where('status', 'Ошибка')->whereBetween('created_at', $month)->count()]]);
    }

    public function text(Request $request): RedirectResponse
    {
        $data = $request->validate(['source_text' => ['required', 'string'], 'secondary_text' => ['nullable', 'string'], 'action' => ['required', Rule::in(array_keys(self::ACTIONS))], 'custom_instruction' => ['nullable', 'string'], 'include_project' => ['nullable', 'boolean'], 'document_ids' => ['array'], 'document_ids.*' => ['exists:documents,id'], 'scene_ids' => ['array'], 'scene_ids.*' => ['exists:scenes,id'], 'shot_ids' => ['array'], 'shot_ids.*' => ['exists:shots,id'], 'asset_ids' => ['array', 'max:4'], 'asset_ids.*' => ['exists:assets,id'], 'subject_type' => ['nullable', Rule::in(['document', 'project', 'scene', 'shot'])], 'subject_id' => ['nullable', 'integer'], 'target_field' => ['nullable', 'string', 'max:80']]);
        try {
            $this->budget->assertCanRequest($request->user());
        } catch (OpenAiException $e) {
            return back()->withErrors(['openai' => $e->getMessage()])->withInput();
        }
        $context = $this->context->build($data);
        $action = self::ACTIONS[$data['action']];
        $prompt = "Задача: {$action}.\n".($data['custom_instruction'] ?? '')."\n\nИСХОДНЫЙ ТЕКСТ:\n{$data['source_text']}".(filled($data['secondary_text'] ?? null) ? "\n\nВТОРАЯ ВЕРСИЯ ДЛЯ СРАВНЕНИЯ:\n{$data['secondary_text']}" : '').($context ? "\n\nВЫБРАННЫЙ КОНТЕКСТ:\n{$context}" : '');
        $subject = $this->subject($data['subject_type'] ?? null, $data['subject_id'] ?? null);
        $conversation = AiConversation::create(['project_id' => Project::firstOrFail()->id, 'user_id' => $request->user()->id, 'title' => $action]);
        $aiRequest = AiRequest::create(['conversation_id' => $conversation->id, 'user_id' => $request->user()->id, 'subject_type' => $subject?->getMorphClass(), 'subject_id' => $subject?->getKey(), 'target_field' => $data['target_field'] ?? null, 'request_type' => 'Текст', 'action' => $data['action'], 'model' => config('openai.text_model') ?: 'не настроена', 'source_text' => $data['source_text'], 'prompt' => $prompt, 'context_snapshot' => $context, 'status' => 'Выполняется']);
        AiMessage::create(['conversation_id' => $conversation->id, 'request_id' => $aiRequest->id, 'user_id' => $request->user()->id, 'role' => 'user', 'content' => $prompt]);
        try {
            $result = $this->openai->text('Вы — помощник команды фильма «Тихий спутник». Работайте только с переданным контекстом. Не выдавайте предложение за утверждённое решение. Возвращайте только новый текст без служебного вступления.', $prompt, $this->imageContext($data['asset_ids'] ?? []));
            DB::transaction(function () use ($aiRequest, $conversation, $request, $result) {
                $aiRequest->update(['model' => $result['model'], 'result_text' => $result['text'], 'status' => 'Завершён', 'api_request_id' => $result['request_id'], 'input_tokens' => $result['input_tokens'], 'output_tokens' => $result['output_tokens'], 'cost' => $result['cost'], 'duration_ms' => $result['duration_ms']]);
                AiMessage::create(['conversation_id' => $conversation->id, 'request_id' => $aiRequest->id, 'role' => 'assistant', 'content' => $result['text']]);
                AiUsageRecord::create(['request_id' => $aiRequest->id, 'user_id' => $request->user()->id, 'model' => $result['model'], 'usage_type' => 'Текст', 'input_tokens' => $result['input_tokens'], 'output_tokens' => $result['output_tokens'], 'cost' => $result['cost'], 'usage_date' => today()]);
            });
        } catch (OpenAiException $e) {
            $aiRequest->update(['status' => 'Ошибка', 'error_message' => $e->getMessage()]);

            return redirect()->route('ai.show', $aiRequest)->withErrors(['openai' => $e->getMessage()]);
        }

        return redirect()->route('ai.show', $aiRequest);
    }

    public function images(Request $request): RedirectResponse
    {
        $max = config('production.max_upload_kb');
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'description' => ['required', 'string'], 'image_type' => ['required', 'string', 'max:100'], 'style' => ['nullable', 'string'], 'size' => ['required', Rule::in(['1024x1024', '1536x1024', '1024x1536', '2048x2048', '2048x1152', '3840x2160', '2160x3840'])], 'quality' => ['required', Rule::in(['low', 'medium', 'high'])], 'count' => ['required', 'integer', 'min:1', 'max:4'], 'additional_instructions' => ['nullable', 'string'], 'source_asset_id' => ['nullable', 'exists:assets,id'], 'reference_asset_ids' => ['array', 'max:4'], 'reference_asset_ids.*' => ['exists:assets,id'], 'reference_files' => ['array', 'max:4'], 'reference_files.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', "max:$max"], 'character_ids' => ['array'], 'character_ids.*' => ['exists:characters,id'], 'location_ids' => ['array'], 'location_ids.*' => ['exists:locations,id'], 'scene_ids' => ['array'], 'scene_ids.*' => ['exists:scenes,id'], 'shot_ids' => ['array'], 'shot_ids.*' => ['exists:shots,id'], 'checklist_item_ids' => ['array'], 'checklist_item_ids.*' => ['exists:checklist_items,id']]);
        try {
            $this->budget->assertCanRequest($request->user(), (float) (config('openai.image_costs.'.$data['quality']) ?? 0) * $data['count']);
        } catch (OpenAiException $e) {
            return back()->withErrors(['openai' => $e->getMessage()])->withInput();
        }
        $referenceIds = $data['reference_asset_ids'] ?? [];
        foreach ($request->file('reference_files', []) as $file) {
            $reference = Asset::create(['title' => 'Референс: '.$file->getClientOriginalName(), 'type' => 'Фото', 'status' => 'Загружено', 'is_private' => true, 'uploaded_by' => $request->user()->id, 'source' => 'Референс для OpenAI'] + $this->assetStorage->store($file));
            $referenceIds[] = $reference->id;
        }
        $data['reference_asset_ids'] = array_values(array_unique($referenceIds));
        unset($data['reference_files']);
        $hasImageInputs = filled($data['source_asset_id'] ?? null) || $data['reference_asset_ids'] !== [];
        $prompt = "Тип: {$data['image_type']}. Описание: {$data['description']}. Стиль: ".($data['style'] ?? 'визуальный стиль фильма «Тихий спутник»').'. '.($data['additional_instructions'] ?? '').($hasImageInputs ? ' Используйте переданные изображения как визуальные референсы и не изменяйте оригиналы.' : '');
        $conversation = AiConversation::create(['project_id' => Project::firstOrFail()->id, 'user_id' => $request->user()->id, 'title' => 'Изображение: '.$data['title']]);
        $aiRequest = AiRequest::create(['conversation_id' => $conversation->id, 'user_id' => $request->user()->id, 'request_type' => $hasImageInputs ? 'Редактирование изображения' : 'Генерация изображения', 'action' => 'image', 'model' => config('openai.image_model'), 'prompt' => $prompt, 'status' => 'В очереди']);
        GenerateAiImages::dispatch($aiRequest->id, [...$data, 'prompt' => $prompt]);

        return redirect()->route('ai.show', $aiRequest)->with('success', 'Запрос изображения поставлен в очередь.');
    }

    public function show(AiRequest $aiRequest): View
    {
        abort_unless($aiRequest->user_id === auth()->id(), 403);

        return view('ai.show', ['request' => $aiRequest->load('generatedAssets.asset')]);
    }

    public function decide(Request $request, AiRequest $aiRequest): RedirectResponse
    {
        abort_unless($aiRequest->user_id === $request->user()->id, 403);
        $data = $request->validate(['decision' => ['required', Rule::in(['apply', 'save_version', 'reject', 'revise', 'copy'])]]);
        if (in_array($data['decision'], ['apply', 'save_version'], true)) {
            $this->applyResult($aiRequest, $request->user()->id);
        }
        $aiRequest->update(['decision' => $data['decision']]);
        if ($data['decision'] === 'revise') {
            return redirect()->route('ai.index')->withInput(['source_text' => $aiRequest->result_text]);
        }

        return back()->with('success', 'Решение сохранено.');
    }

    private function subject(?string $type, ?int $id)
    {
        if (! $type || ! $id) {
            return null;
        }$class = match ($type) {
            'document' => Document::class,'project' => Project::class,'scene' => Scene::class,'shot' => Shot::class
        };

        return $class::findOrFail($id);
    }

    private function imageContext(array $ids): array
    {
        $images = [];
        foreach (Asset::whereIn('id', $ids)->get() as $asset) {
            if (! $asset->file_path || ! str_starts_with((string) $asset->mime_type, 'image/') || ! Storage::disk($asset->disk)->exists($asset->file_path)) {
                continue;
            }
            $bytes = Storage::disk($asset->disk)->get($asset->file_path);
            if (strlen($bytes) <= 20 * 1024 * 1024) {
                $images[] = ['mime_type' => $asset->mime_type, 'bytes' => $bytes];
            }
        }

        return $images;
    }

    private function applyResult(AiRequest $request, int $userId): void
    {
        abort_if(blank($request->result_text) || ! $request->subject, 422);
        $subject = $request->subject;
        $field = $request->target_field;
        $allowed = $subject instanceof Document ? ['content'] : ($subject instanceof Project ? ['tagline', 'logline', 'synopsis', 'visual_principle', 'sound_principle', 'color_palette', 'camera_rules'] : ($subject instanceof Scene ? ['action_description', 'dialogue', 'sounds', 'notes'] : ['description', 'hero_action', 'dog_action', 'camera_position', 'camera_movement', 'sound', 'dialogue', 'prompt', 'negative_prompt', 'notes']));
        abort_unless(in_array($field, $allowed, true), 422, 'Поле нельзя изменить через ИИ.');
        $subject->{$field} = $request->result_text;
        if ($subject instanceof Document) {
            $subject->version++;
            $subject->updated_by = $userId;
            $subject->save();
            $subject->versions()->create(['user_id' => $userId, 'version' => $subject->version, 'content' => $subject->content, 'change_note' => 'Принято предложение OpenAI, запрос #'.$request->id]);
        } elseif ($subject instanceof Project) {
            $subject->version++;
            $subject->updated_by = $userId;
            $subject->save();
            ProjectVersion::create(['project_id' => $subject->id, 'version' => $subject->version, 'snapshot' => $subject->toArray(), 'user_id' => $userId]);
        } else {
            $subject->save();
        }
    }
}
