<?php

namespace App\Services;

use App\Repositories\ChatRepository;
use App\Repositories\SubscriptionRepository;
use Illuminate\Support\Arr;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SubscriptionService
{
    public function __construct(
        protected SubscriptionRepository $subscriptions,
        protected ChatRepository $chats,
    ) {
    }

    public function assertCanUseChat(): void
    {
        $subscription = $this->subscriptions->active();

        if (! $subscription) {
            throw new HttpException(402, 'An active subscription is required.');
        }

        $chatLimit = (int) Arr::get($subscription->plan?->limits_json, 'monthly_chat_requests', 0);

        if ($chatLimit > 0 && $this->chats->countCurrentMonth() >= $chatLimit) {
            throw new HttpException(429, 'Monthly chat limit reached.');
        }
    }
}
