<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotEmbed extends TenantModel
{
    protected $fillable = [
        'tenant_id',
        'chatbot_id',
        'widget_key',
        'allowed_domains',
        'launcher_text',
        'auto_open',
        'bubble_icon',
    ];

    protected $casts = [
        'allowed_domains' => 'array',
        'auto_open' => 'boolean',
    ];

    public function chatbot(): BelongsTo
    {
        return $this->belongsTo(Chatbot::class);
    }
}
