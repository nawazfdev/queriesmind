<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Website extends TenantModel
{
    protected $fillable = [
        'tenant_id',
        'chatbot_id',
        'url',
        'name',
        'status',
        'content',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function chatbot(): BelongsTo
    {
        return $this->belongsTo(Chatbot::class);
    }
}
