<?php

namespace App\Providers;

use App\Services\Contracts\AIServiceInterface;
use App\Services\OpenRouterAIService;
use App\Services\TenantContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->bind(AIServiceInterface::class, OpenRouterAIService::class);
    }

    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        RateLimiter::for('tenant-api', function (Request $request) {
            $tenant = app(TenantContext::class)->getTenant();
            $key = $tenant?->getKey() ?? $request->ip();
            $maxAttempts = (int) config('querymind.rate_limits.per_minute', 60);

            return [
                Limit::perMinute($maxAttempts)->by('tenant:'.$key),
            ];
        });

        Gate::before(function ($user, string $ability) {
            return method_exists($user, 'hasRole') && $user->hasRole('super_admin', 'api')
                ? true
                : null;
        });
    }
}
