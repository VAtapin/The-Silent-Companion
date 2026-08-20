<?php

namespace App\Jobs;

use App\Models\AiGeneratedAsset;
use App\Models\AiRequest;
use App\Models\AiUsageRecord;
use App\Models\Asset;
use App\Models\User;
use App\Services\ChecklistProgressService;
use App\Services\OpenAiBudgetService;
use App\Services\OpenAiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateAiImages implements ShouldQueue
{
    use Queueable;

    public int $timeout = 360;

    public int $tries = 2;

    public function __construct(public int $requestId, public array $options) {}

    public function handle(OpenAiService $openai, OpenAiBudgetService $budget, ChecklistProgressService $checklist): void
    {
        $request = AiRequest::findOrFail($this->requestId);
        $user = User::findOrFail($request->user_id);
        $quality = $this->options['quality'];
        $count = (int) $this->options['count'];
        $budget->assertCanRequest($user, (float) (config("openai.image_costs.$quality") ?? 0) * $count, $request->id);
        $request->update(['status' => 'Выполняется']);
        try {
            $source = isset($this->options['source_asset_id']) ? Asset::find($this->options['source_asset_id']) : null;
            $references = Asset::whereIn('id', $this->options['reference_asset_ids'] ?? [])->get();
            $inputAssets = collect([$source])->filter()->concat($references)->unique('id')->filter(fn (Asset $asset) => $asset->file_path && str_starts_with((string) $asset->mime_type, 'image/'));
            $inputImages = $inputAssets->map(fn (Asset $asset) => ['bytes' => Storage::disk($asset->disk)->get($asset->file_path), 'filename' => $asset->original_name ?: 'reference.png'])->values()->all();
            $result = $inputImages !== []
                ? $openai->editImage($request->prompt, $inputImages, $this->options['size'], $quality, $count)
                : $openai->generateImages($request->prompt, $this->options['size'], $quality, $count);
            foreach ($result['images'] as $index => $bytes) {
                $disk = config('production.asset_disk');
                $path = 'assets/ai/'.now()->format('Y/m').'/'.Str::uuid().'.png';
                Storage::disk($disk)->put($path, $bytes);
                $asset = Asset::create(['title' => $this->options['title'].' — вариант '.($index + 1), 'description' => $this->options['description'] ?? null, 'type' => 'Фото', 'status' => 'Создано ИИ', 'is_private' => true, 'disk' => $disk, 'file_path' => $path, 'original_name' => 'openai-'.($index + 1).'.png', 'mime_type' => 'image/png', 'size_bytes' => strlen($bytes), 'uploaded_by' => $user->id, 'source' => 'OpenAI API', 'has_usage_permission' => true, 'comment' => 'Создано моделью '.$result['model']]);
                foreach (['characters' => 'character_ids', 'locations' => 'location_ids', 'scenes' => 'scene_ids', 'shots' => 'shot_ids', 'checklistItems' => 'checklist_item_ids'] as $relation => $key) {
                    $asset->{$relation}()->sync($this->options[$key] ?? []);
                }
                AiGeneratedAsset::create(['request_id' => $request->id, 'asset_id' => $asset->id, 'source_asset_id' => $source?->id, 'settings' => ['size' => $this->options['size'], 'quality' => $quality, 'style' => $this->options['style'] ?? null, 'reference_asset_ids' => $references->pluck('id')->all()]]);
                $checklist->recalculateForAsset($asset);
            }
            $request->update(['status' => 'Завершён', 'api_request_id' => $result['request_id'], 'image_count' => count($result['images']), 'cost' => $result['cost'], 'duration_ms' => $result['duration_ms']]);
            AiUsageRecord::create(['request_id' => $request->id, 'user_id' => $user->id, 'model' => $result['model'], 'usage_type' => $inputImages !== [] ? 'Редактирование изображения' : 'Генерация изображения', 'images' => count($result['images']), 'cost' => $result['cost'], 'usage_date' => today()]);
        } catch (\Throwable $e) {
            $request->update(['status' => 'Ошибка', 'error_message' => $e->getMessage()]);
            throw $e;
        }
    }
}
