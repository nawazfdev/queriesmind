<?php

namespace App\Repositories;

use App\Models\Tenant;

class TenantRepository
{
    public function findByPublicKey(string $plainKey): ?Tenant
    {
        return Tenant::query()
            ->where('api_key', hash('sha256', $plainKey))
            ->first();
    }

    public function findByPrivateKey(string $plainKey): ?Tenant
    {
        return Tenant::query()
            ->where('private_api_key', hash('sha256', $plainKey))
            ->first();
    }
}
