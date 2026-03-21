<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotAppearance extends TenantModel
{
    protected $fillable = [
        'tenant_id',
        'chatbot_id',
        'theme_color',
        'text_color',
        'position',
        'border_radius',
        'avatar_url',
        'show_branding',
        'custom_css',
    ];

    protected $casts = [
        'show_branding' => 'boolean',
    ];

    public function chatbot(): BelongsTo
    {
        return $this->belongsTo(Chatbot::class);
    }
}
