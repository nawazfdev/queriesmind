<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function checkout(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $tenant = $request->attributes->get('current_tenant');
        $plan = Plan::query()->findOrFail($request->plan_id);

        Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'expires_at' => now()->addMonth(),
        ]);

        $checkoutUrl = 'https://checkout.stripe.com/mock/'.str($plan->name)->slug();

        return response()->json([
            'checkout_url' => $checkoutUrl,
            'plan' => [
                'name' => $plan->name,
                'limits' => $plan->limits_json,
            ],
        ]);
    }
}
