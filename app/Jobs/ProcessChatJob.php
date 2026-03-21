<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\ChatService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessChatJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $tenantId,
        public array $payload,
        public ?int $userId = null,
    ) {
    }

    public function handle(ChatService $chatService): void
    {
        $tenant = Tenant::query()->findOrFail($this->tenantId);
        app(\App\Services\TenantContext::class)->setTenant($tenant);

        $chatService->handle($tenant, $this->payload, $this->userId);
    }
}
