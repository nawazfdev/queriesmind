<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Chat extends TenantModel
{
    protected $fillable = [
        'public_id',
        'tenant_id',
        'chatbot_id',
        'user_id',
        'session_id',
        'question',
        'answer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $chat): void {
            $chat->public_id ??= (string) str()->uuid();
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chatbot(): BelongsTo
    {
        return $this->belongsTo(Chatbot::class);
    }
}
