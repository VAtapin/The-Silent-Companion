<?php

namespace App\Services;

use App\Exceptions\OpenAiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class OpenAiService
{
    public function text(string $instructions, string $input, array $images = []): array
    {
        $model = config('openai.text_model');
        $this->ensureConfigured($model);
        $started = hrtime(true);
        try {
            $apiInput = $input;
            if ($images !== []) {
                $content = [['type' => 'input_text', 'text' => $input]];
                foreach ($images as $image) {
                    $content[] = [
                        'type' => 'input_image',
                        'image_url' => 'data:'.$image['mime_type'].';base64,'.base64_encode($image['bytes']),
                    ];
                }
                $apiInput = [['role' => 'user', 'content' => $content]];
            }
            $response = $this->client()->post('/responses', ['model' => $model, 'instructions' => $instructions, 'input' => $apiInput]);
        } catch (\Throwable $exception) {
            throw new OpenAiException('OpenAI временно недоступен: '.$exception->getMessage(), previous: $exception);
        }
        if (! $response->successful()) {
            throw new OpenAiException($this->safeError($response->json(), $response->status()));
        }
        $json = $response->json();
        $text = collect($json['output'] ?? [])->flatMap(fn ($item) => $item['content'] ?? [])->firstWhere('type', 'output_text')['text'] ?? null;
        if (blank($text)) {
            throw new OpenAiException('OpenAI не вернул текстовый результат. Исходный текст сохранён без изменений.');
        }
        $inputTokens = (int) data_get($json, 'usage.input_tokens', 0);
        $outputTokens = (int) data_get($json, 'usage.output_tokens', 0);

        return ['text' => $text, 'model' => $model, 'request_id' => $json['id'] ?? null, 'input_tokens' => $inputTokens, 'output_tokens' => $outputTokens, 'cost' => $this->textCost($inputTokens, $outputTokens), 'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000)];
    }

    public function generateImages(string $prompt, string $size, string $quality, int $count): array
    {
        $model = config('openai.image_model');
        $this->ensureConfigured($model);
        $started = hrtime(true);
        try {
            $response = $this->client()->post('/images/generations', ['model' => $model, 'prompt' => $prompt, 'size' => $size, 'quality' => $quality, 'n' => $count]);
        } catch (\Throwable $e) {
            throw new OpenAiException('OpenAI не смог создать изображение: '.$e->getMessage(), previous: $e);
        }
        if (! $response->successful()) {
            throw new OpenAiException($this->safeError($response->json(), $response->status()));
        }
        $json = $response->json();
        $images = [];
        foreach ($json['data'] ?? [] as $entry) {
            $bytes = base64_decode($entry['b64_json'] ?? '', true);
            if ($bytes !== false && $bytes !== '') {
                $images[] = $bytes;
            }
        }
        if ($images === []) {
            throw new OpenAiException('OpenAI не вернул данные изображения.');
        }

        return ['images' => $images, 'model' => $model, 'request_id' => $json['id'] ?? null, 'cost' => (config("openai.image_costs.$quality") ?? 0) * count($images), 'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000)];
    }

    public function editImage(string $prompt, array $images, string $size, string $quality, int $count = 1): array
    {
        $model = config('openai.image_model');
        $this->ensureConfigured($model);
        $started = hrtime(true);
        try {
            $client = $this->client();
            foreach ($images as $image) {
                $client->attach('image[]', $image['bytes'], $image['filename']);
            }
            $response = $client->post('/images/edits', ['model' => $model, 'prompt' => $prompt, 'size' => $size, 'quality' => $quality, 'n' => $count]);
        } catch (\Throwable $e) {
            throw new OpenAiException('OpenAI не смог отредактировать изображение: '.$e->getMessage(), previous: $e);
        }
        if (! $response->successful()) {
            throw new OpenAiException($this->safeError($response->json(), $response->status()));
        }
        $json = $response->json();
        $decoded = [];
        foreach ($json['data'] ?? [] as $entry) {
            $image = base64_decode($entry['b64_json'] ?? '', true);
            if ($image !== false && $image !== '') {
                $decoded[] = $image;
            }
        }
        if ($decoded === []) {
            throw new OpenAiException('OpenAI не вернул отредактированное изображение.');
        }

        return ['images' => $decoded, 'model' => $model, 'request_id' => $json['id'] ?? null, 'cost' => (float) (config("openai.image_costs.$quality") ?? 0) * count($decoded), 'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000)];
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('openai.base_url'), '/'))->withToken(config('openai.api_key'))->acceptJson()->timeout(config('openai.timeout'))->retry(2, 500, throw: false);
    }

    private function ensureConfigured(?string $model): void
    {
        if (blank(config('openai.api_key'))) {
            throw new OpenAiException('OpenAI API не настроен: добавьте OPENAI_API_KEY в .env.');
        }
        if (blank($model)) {
            throw new OpenAiException('Модель OpenAI не настроена в .env.');
        }
    }

    private function safeError(?array $json, int $status): string
    {
        return 'Ошибка OpenAI ('.$status.'): '.(data_get($json, 'error.message') ?: 'сервис не вернул описание');
    }

    private function textCost(int $input, int $output): float
    {
        return round($input / 1_000_000 * config('openai.text_input_cost_per_million') + $output / 1_000_000 * config('openai.text_output_cost_per_million'), 6);
    }
}
