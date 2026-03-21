<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules;

class AuthController extends Controller
{
    public function __construct(protected TenantService $tenantService)
    {
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'tenant_name' => ['nullable', 'string', 'max:255'],
            'allowed_domains' => ['nullable', 'array'],
            'allowed_domains.*' => ['string'],
        ]);

        $defaultPlan = \App\Models\Plan::query()->orderBy('id')->first();

        if (! $defaultPlan) {
            throw ValidationException::withMessages([
                'plan' => 'No plan is available for tenant registration.',
            ]);
        }

        $keys = $this->tenantService->generateApiKeys();

        $tenant = Tenant::create([
            'name' => $request->input('tenant_name', $request->name."'s Workspace"),
            'api_key' => $keys['public_hash'],
            'private_api_key' => $keys['private_hash'],
            'plan_id' => $defaultPlan->id,
            'allowed_domains' => $request->input('allowed_domains', []),
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'tenant_id' => $tenant->id,
            'role' => 'tenant_admin',
        ]);

        $user->assignRole('tenant_admin');

        return response()->json(
            $this->tokenResponse(
                $user,
                $tenant,
                $user->createToken('API Token')->accessToken,
                'Account created successfully.',
                [
                    'public_api_key' => $keys['public'],
                    'private_api_key' => $keys['private'],
                ]
            ),
            201
        );
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::query()->where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return response()->json(
            $this->tokenResponse(
                $user,
                $user->tenant,
                $user->createToken('API Token')->accessToken,
                'Login successful.'
            )
        );
    }

    public function logout(Request $request)
    {
        $token = $request->user()?->token();

        if ($token) {
            $token->revoke();
        }

        return response()->json(['message' => 'Successfully logged out.']);
    }

    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->update(['revoked' => true]);

        return response()->json(['message' => 'All tokens have been revoked.']);
    }

    public function user(Request $request)
    {
        $user = $request->user()->load('tenant.plan');

        return response()->json([
            'user' => $user,
            'tenant' => $request->attributes->get('current_tenant') ?? $user->tenant,
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ]);
    }

    protected function tokenResponse(User $user, ?Tenant $tenant, string $token, string $message, array $meta = []): array
    {
        $user->load('tenant.plan');

        return [
            'message' => $message,
            'token_type' => 'Bearer',
            'access_token' => $token,
            'user' => $user,
            'tenant' => $tenant?->load('plan'),
            'meta' => array_merge($meta, [
                'roles' => $user->getRoleNames()->values(),
                'permissions' => $user->getAllPermissions()->pluck('name')->values(),
                'is_super_admin' => $user->hasRole('super_admin', 'api'),
            ]),
        ];
    }
}
