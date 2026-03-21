<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatbotTrainingSource extends TenantModel
{
    protected $fillable = [
        'tenant_id',
        'chatbot_id',
        'source_type',
        'title',
        'source_reference',
        'status',
        'meta_json',
        'last_trained_at',
    ];

    protected $casts = [
        'meta_json' => 'array',
        'last_trained_at' => 'datetime',
    ];

    public function chatbot(): BelongsTo
    {
        return $this->belongsTo(Chatbot::class);
    }
}
