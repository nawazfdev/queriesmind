<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'api_key',
        'private_api_key',
        'plan_id',
        'allowed_domains',
        'is_active',
    ];

    protected $casts = [
        'allowed_domains' => 'array',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'api_key',
        'private_api_key',
        'id',
        'plan_id',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function chats(): HasMany
    {
        return $this->hasMany(Chat::class);
    }

    public function chatbots(): HasMany
    {
        return $this->hasMany(Chatbot::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function embeddings(): HasMany
    {
        return $this->hasMany(Embedding::class);
    }

    public function websites(): HasMany
    {
        return $this->hasMany(Website::class);
    }

    public function chatbotTrainingSources(): HasMany
    {
        return $this->hasMany(ChatbotTrainingSource::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
