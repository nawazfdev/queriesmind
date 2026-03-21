<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Plan::query()->create([
            'name' => 'Starter',
            'limits_json' => [
                'monthly_chat_requests' => 1000,
                'document_limit' => 10,
                'rate_limit_per_minute' => 30,
            ],
        ]);

        Plan::query()->create([
            'name' => 'Pro',
            'limits_json' => [
                'monthly_chat_requests' => 10000,
                'document_limit' => 100,
                'rate_limit_per_minute' => 120,
            ],
        ]);
    }
}
