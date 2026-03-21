<?php

namespace App\Scopes;

use App\Exceptions\MissingTenantContextException;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        /** @var TenantContext $tenantContext */
        $tenantContext = app(TenantContext::class);
        $tenant = $tenantContext->getTenant();

        if ($tenant) {
            $builder->where($model->qualifyColumn('tenant_id'), $tenant->getKey());

            return;
        }

        if ($this->shouldSkipEnforcement($model)) {
            return;
        }

        throw new MissingTenantContextException(sprintf(
            'Tenant context is required before querying [%s].',
            $model::class
        ));
    }

    protected function shouldSkipEnforcement(Model $model): bool
    {
        if (app()->runningInConsole() || app()->runningUnitTests()) {
            return true;
        }

        if (auth()->check() && method_exists(auth()->user(), 'hasRole') && auth()->user()->hasRole('super_admin', 'api')) {
            return true;
        }

        return method_exists($model, 'requiresTenantContext') && ! $model->requiresTenantContext();
    }
}
