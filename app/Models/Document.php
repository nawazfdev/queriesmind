<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends TenantModel
{
    protected $fillable = [
        'tenant_id',
        'chatbot_id',
        'title',
        'source_type',
        'source_url',
        'file_path',
        'content',
        'meta_json',
        'status',
    ];

    protected $casts = [
        'meta_json' => 'array',
    ];

    public function embeddings(): HasMany
    {
        return $this->hasMany(Embedding::class);
    }

    public function chatbot(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Chatbot::class);
    }
}
