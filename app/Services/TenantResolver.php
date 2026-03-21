<?php

namespace App\Services;

use App\Models\Chatbot;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\TenantRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class TenantResolver
{
    public function __construct(
        protected TenantRepository $tenants,
        protected TenantContext $tenantContext,
    ) {
    }

    public function resolve(Request $request): Tenant
    {
        if ($request->user() && method_exists($request->user(), 'hasRole') && $request->user()->hasRole('super_admin', 'api')) {
            throw new UnauthorizedHttpException('ApiKey', 'Super admin requests do not use tenant-scoped routes.');
        }

        $tenant = $this->resolveFromAuthenticatedUser($request->user())
            ?? $this->resolveFromApiKey($request)
            ?? $this->resolveFromChatbot($request);

        if (! $tenant || ! $tenant->is_active) {
            throw new UnauthorizedHttpException('ApiKey', 'A valid tenant context could not be resolved.');
        }

        $this->tenantContext->setTenant($tenant);
        $request->attributes->set('current_tenant', $tenant);

        return $tenant;
    }

    protected function resolveFromAuthenticatedUser(?User $user): ?Tenant
    {
        if (! $user) {
            return null;
        }

        return $user->tenant()->first();
    }

    protected function resolveFromApiKey(Request $request): ?Tenant
    {
        $publicKey = $request->header('X-QueryMind-Key', $request->input('api_key'));
        $privateKey = $request->header('X-QueryMind-Secret');

        if ($privateKey) {
            return $this->tenants->findByPrivateKey($privateKey);
        }

        if (! $publicKey) {
            return null;
        }

        $tenant = $this->tenants->findByPublicKey($publicKey);

        if ($tenant) {
            $this->assertAllowedDomain($request, $tenant);
        }

        return $tenant;
    }

    protected function resolveFromChatbot(Request $request): ?Tenant
    {
        $chatbotId = $request->input('chatbot_id');

        if (! filled($chatbotId)) {
            return null;
        }

        $chatbot = Chatbot::query()
            ->withoutGlobalScopes()
            ->where('id', $chatbotId)
            ->orWhere('public_id', $chatbotId)
            ->first();

        if (! $chatbot) {
            return null;
        }

        $tenant = $chatbot->tenant()->first();

        if ($tenant) {
            $this->assertAllowedDomain($request, $tenant);
        }

        return $tenant;
    }

    protected function assertAllowedDomain(Request $request, Tenant $tenant): void
    {
        $origin = $request->headers->get('Origin') ?: $request->headers->get('Referer');

        if (! $origin) {
            return;
        }

        $host = parse_url($origin, PHP_URL_HOST);

        if (! $host) {
            throw new AccessDeniedHttpException('Invalid widget origin.');
        }

        $allowedDomains = collect($tenant->allowed_domains ?? [])
            ->filter()
            ->map(fn (string $domain) => Str::lower($domain))
            ->values();

        if ($allowedDomains->isEmpty()) {
            return;
        }

        if (! $allowedDomains->contains(Str::lower($host))) {
            throw new AccessDeniedHttpException('Origin is not allowed for this tenant.');
        }
    }
}
