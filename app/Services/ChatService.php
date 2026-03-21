<?php

namespace App\Services;

use App\Models\Tenant;
use App\Repositories\ChatRepository;
use App\Services\Contracts\AIServiceInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

class ChatService
{
    public function __construct(
        protected SubscriptionService $subscriptions,
        protected EmbeddingService $embeddings,
        protected AIServiceInterface $ai,
        protected ChatRepository $chats,
    ) {
    }

    public function handle(Tenant $tenant, array $payload, ?int $userId = null): array
    {
        $this->subscriptions->assertCanUseChat();

        $question = trim($payload['question']);
        $chatbotId = $payload['chatbot_id'] ?? null;
        $sessionId = $payload['session_id'] ?? (string) str()->uuid();
        $cacheKey = $this->cacheKey($tenant->getKey(), $chatbotId, $question, $sessionId);
        $store = $this->cacheStore();
        $fromCache = $store->has($cacheKey);

        $answer = $store->get($cacheKey);

        if (! $fromCache || ! $answer) {
            $context = $this->embeddings->search($question, $chatbotId);
            $answer = $this->ai->generateAnswer($question, $context, [
                'tenant_id' => $tenant->getKey(),
                'chatbot_id' => $chatbotId,
                'session_id' => $sessionId,
            ]);

            $store->put($cacheKey, $answer, now()->addMinutes(10));
        }

        $chat = $this->chats->create([
            'tenant_id' => $tenant->getKey(),
            'chatbot_id' => $chatbotId,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'question' => $question,
            'answer' => $answer,
        ]);

        return [
            'session_id' => $sessionId,
            'answer' => $answer,
            'cached' => $fromCache,
            'chat_uuid' => $chat->public_id,
        ];
    }

    protected function cacheKey(int $tenantId, ?int $chatbotId, string $question, string $sessionId): string
    {
        return 'querymind:chat:'.sha1($tenantId.'|'.$chatbotId.'|'.$sessionId.'|'.$question);
    }

    protected function cacheStore(): CacheRepository
    {
        $preferredStore = (string) config('querymind.cache_store', config('cache.default', 'database'));

        if ($preferredStore === 'redis' && $this->redisPhpExtensionMissing()) {
            return Cache::store((string) config('cache.default', 'database'));
        }

        return Cache::store($preferredStore);
    }

    protected function redisPhpExtensionMissing(): bool
    {
        return config('database.redis.client') === 'phpredis' && ! extension_loaded('redis');
    }
}
