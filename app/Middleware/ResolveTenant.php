<?php

namespace App\Middleware;

use App\Services\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(protected TenantResolver $tenantResolver)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->tenantResolver->resolve($request);

        return $next($request);
    }
}
