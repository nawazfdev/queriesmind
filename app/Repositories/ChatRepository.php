<?php

namespace App\Repositories;

use App\Models\Chat;

class ChatRepository
{
    public function create(array $attributes): Chat
    {
        return Chat::query()->create($attributes);
    }

    public function countCurrentMonth(): int
    {
        return Chat::query()
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
    }
}
