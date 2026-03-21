<?php

namespace App\Repositories;

use App\Models\Subscription;

class SubscriptionRepository
{
    public function active(): ?Subscription
    {
        return Subscription::query()
            ->with('plan')
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latest('id')
            ->first();
    }
}
