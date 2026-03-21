<?php

namespace App\Traits;

use App\Exceptions\MissingTenantContextException;
use App\Exceptions\TenantAccessDeniedException;
use App\Models\Tenant;
use App\Scopes\TenantScope;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function (Model $model): void {
            /** @var TenantContext $tenantContext */
            $tenantContext = app(TenantContext::class);
            $tenant = $tenantContext->getTenant();

            if (! $tenant) {
                if ($model->requiresTenantContext()) {
                    throw new MissingTenantContextException(sprintf(
                        'Tenant context is required before creating [%s].',
                        $model::class
                    ));
                }

                return;
            }

            if (! $model->tenant_id) {
                $model->tenant_id = $tenant->getKey();

                return;
            }

            if ((int) $model->tenant_id !== (int) $tenant->getKey()) {
                throw new TenantAccessDeniedException('Cross-tenant writes are not allowed.');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function requiresTenantContext(): bool
    {
        return property_exists($this, 'enforcesTenantContext')
            ? (bool) $this->enforcesTenantContext
            : true;
    }
}
