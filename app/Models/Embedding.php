<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Embedding extends TenantModel
{
    protected $fillable = [
        'tenant_id',
        'chatbot_id',
        'document_id',
        'chunk_index',
        'vector_reference',
        'source_url',
        'content_text',
        'meta_json',
    ];

    protected $casts = [
        'meta_json' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function chatbot(): BelongsTo
    {
        return $this->belongsTo(Chatbot::class);
    }
}
