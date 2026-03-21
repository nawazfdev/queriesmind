<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

class PlatformCustomerController extends Controller
{
    public function index(): JsonResponse
    {
        $customers = Tenant::query()
            ->with('plan')
            ->withCount(['users', 'chats', 'documents', 'subscriptions'])
            ->orderBy('name')
            ->get()
            ->map(fn (Tenant $tenant) => [
                'tenant_id' => $tenant->getKey(),
                'name' => $tenant->name,
                'plan' => $tenant->plan?->name,
                'is_active' => $tenant->is_active,
                'allowed_domains' => $tenant->allowed_domains,
                'users_count' => $tenant->users_count,
                'chats_count' => $tenant->chats_count,
                'documents_count' => $tenant->documents_count,
                'subscriptions_count' => $tenant->subscriptions_count,
                'created_at' => $tenant->created_at,
            ]);

        return response()->json([
            'data' => $customers,
        ]);
    }

    public function show(Tenant $tenant): JsonResponse
    {
        $tenant->load(['plan', 'users', 'subscriptions.plan']);
        $tenant->loadCount(['chats', 'documents', 'embeddings']);

        return response()->json([
            'data' => [
                'tenant_id' => $tenant->getKey(),
                'name' => $tenant->name,
                'plan' => $tenant->plan?->only(['id', 'name', 'limits_json']),
                'is_active' => $tenant->is_active,
                'allowed_domains' => $tenant->allowed_domains,
                'stats' => [
                    'chats_count' => $tenant->chats_count,
                    'documents_count' => $tenant->documents_count,
                    'embeddings_count' => $tenant->embeddings_count,
                ],
                'users' => $tenant->users->map(fn ($user) => [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ])->values(),
                'subscriptions' => $tenant->subscriptions->map(fn ($subscription) => [
                    'id' => $subscription->getKey(),
                    'status' => $subscription->status,
                    'expires_at' => $subscription->expires_at,
                    'plan' => $subscription->plan?->only(['id', 'name', 'limits_json']),
                ])->values(),
            ],
        ]);
    }
}
