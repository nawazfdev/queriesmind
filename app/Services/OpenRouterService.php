<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenRouterService
{
    public function embed(string $input): array
    {
        $response = $this->client()->post('/embeddings', [
            'model' => config('services.openrouter.embedding_model'),
            'input' => $input,
            'encoding_format' => 'float',
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Embedding request failed: '.$response->body());
        }

        return $response->json('data.0.embedding', []);
    }

    public function chat(array $messages, array $options = []): string
    {
        $payload = array_filter([
            'model' => $options['model'] ?? config('services.openrouter.chat_model'),
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.2,
            'max_tokens' => $options['max_tokens'] ?? 700,
        ], fn ($value) => $value !== null);

        $response = $this->client()->post('/chat/completions', $payload);

        if ($response->failed()) {
            throw new RuntimeException('Chat completion failed: '.$response->body());
        }

        return trim((string) $response->json('choices.0.message.content', ''));
    }

    protected function client()
    {
        $apiKey = config('services.openrouter.api_key');

        if (! $apiKey) {
            throw new RuntimeException('NVIDIA_TEXT_API_KEY is not configured.');
        }

        return Http::baseUrl(rtrim((string) config('services.openrouter.base_url'), '/'))
            ->timeout(60)
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->withHeaders(array_filter([
                'HTTP-Referer' => config('services.openrouter.site_url'),
                'X-Title' => config('services.openrouter.app_name'),
            ]));
    }
}
