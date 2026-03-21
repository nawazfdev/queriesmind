<?php

namespace App\Services;

class TenantService
{
    public function generateApiKeys(): array
    {
        $public = 'qm_pub_'.bin2hex(random_bytes(16));
        $private = 'qm_sec_'.bin2hex(random_bytes(24));

        return [
            'public' => $public,
            'public_hash' => hash('sha256', $public),
            'private' => $private,
            'private_hash' => hash('sha256', $private),
        ];
    }
}
