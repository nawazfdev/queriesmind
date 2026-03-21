<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Chatbot extends TenantModel
{
    protected $fillable = [
        'tenant_id',
        'public_id',
        'name',
        'website_url',
        'status',
        'language',
        'ai_model',
        'personality',
        'system_prompt',
        'welcome_message',
        'fallback_message',
        'temperature',
        'lead_capture_enabled',
        'max_tokens',
    ];

    protected $casts = [
        'temperature' => 'decimal:2',
        'lead_capture_enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $chatbot): void {
            $chatbot->public_id ??= (string) str()->uuid();
        });
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        return $this->newQuery()
            ->when(
                $field,
                fn ($query) => $query->where($field, $value),
                fn ($query) => $query->where('public_id', $value)->orWhere('id', $value)
            )
            ->first();
    }

    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function embeddings(): HasMany
    {
        return $this->hasMany(Embedding::class);
    }

    public function trainingSources(): HasMany
    {
        return $this->hasMany(ChatbotTrainingSource::class);
    }

    public function appearance(): HasOne
    {
        return $this->hasOne(ChatbotAppearance::class);
    }

    public function embed(): HasOne
    {
        return $this->hasOne(ChatbotEmbed::class);
    }
}
