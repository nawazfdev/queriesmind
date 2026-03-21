<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Chatbot;
use App\Models\ChatbotAppearance;
use App\Models\ChatbotEmbed;
use App\Models\ChatbotTrainingSource;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->call(PlanSeeder::class);

        $starterPlan = Plan::query()->orderBy('id')->first();
        $keys = app(TenantService::class)->generateApiKeys();

        foreach ([
            'customers.view',
            'settings.manage',
            'documents.manage',
            'chat.use',
            'subscription.manage',
        ] as $permission) {
            Permission::findOrCreate($permission, 'api');
        }

        $superAdminRole = Role::findOrCreate('super_admin', 'api');
        $tenantAdminRole = Role::findOrCreate('tenant_admin', 'api');
        $memberRole = Role::findOrCreate('member', 'api');

        $superAdminRole->givePermissionTo(Permission::all());
        $tenantAdminRole->givePermissionTo([
            'documents.manage',
            'chat.use',
            'subscription.manage',
        ]);
        $memberRole->givePermissionTo([
            'chat.use',
        ]);

        $tenant = Tenant::query()->firstOrCreate(
            ['name' => 'Demo Tenant'],
            [
                'api_key' => $keys['public_hash'],
                'private_api_key' => $keys['private_hash'],
                'plan_id' => $starterPlan->id,
                'allowed_domains' => ['localhost', 'demo.querymind.test'],
                'is_active' => true,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'admin@sitebotai.test'],
            [
                'name' => 'Demo Admin',
                'password' => Hash::make('password'),
                'tenant_id' => $tenant->id,
                'role' => 'tenant_admin',
            ]
        );

        $tenantAdmin = User::query()->where('email', 'admin@sitebotai.test')->first();
        $tenantAdmin?->syncRoles(['tenant_admin']);

        $superAdmin = User::query()->updateOrCreate(
            ['email' => 'superadmin@querymind.test'],
            [
                'name' => 'QueryMind Super Admin',
                'password' => Hash::make('password'),
                'tenant_id' => null,
                'role' => 'super_admin',
            ]
        );
        $superAdmin->syncRoles(['super_admin']);

        Subscription::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'plan_id' => $starterPlan->id,
            ],
            [
                'status' => 'active',
                'expires_at' => now()->addMonth(),
            ]
        );

        $chatbot = Chatbot::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'name' => 'Demo Chatbot',
            ],
            [
                'public_id' => (string) Str::uuid(),
                'website_url' => 'https://demo.querymind.test',
                'status' => 'active',
                'language' => 'english',
                'ai_model' => 'gpt-4',
                'personality' => 'Casual',
                'welcome_message' => 'Hi! How can I help you today?',
                'fallback_message' => 'I do not have enough information yet.',
                'temperature' => 0.7,
                'lead_capture_enabled' => false,
                'max_tokens' => 1000,
            ]
        );

        ChatbotAppearance::query()->updateOrCreate(
            ['chatbot_id' => $chatbot->id],
            [
                'tenant_id' => $tenant->id,
                'theme_color' => '#2563eb',
                'text_color' => '#0f172a',
                'position' => 'right',
                'border_radius' => '16px',
                'show_branding' => true,
            ]
        );

        ChatbotEmbed::query()->updateOrCreate(
            ['chatbot_id' => $chatbot->id],
            [
                'tenant_id' => $tenant->id,
                'widget_key' => 'qm_widget_demo_'.$tenant->id,
                'allowed_domains' => ['localhost', 'demo.querymind.test'],
                'launcher_text' => 'Chat with us',
                'auto_open' => false,
            ]
        );

        ChatbotTrainingSource::query()->updateOrCreate(
            [
                'chatbot_id' => $chatbot->id,
                'source_reference' => 'https://demo.querymind.test/docs',
            ],
            [
                'tenant_id' => $tenant->id,
                'source_type' => 'website',
                'title' => 'Demo Docs',
                'status' => 'ready',
                'meta_json' => ['seeded' => true],
                'last_trained_at' => now(),
            ]
        );

        foreach ([
            'app_name' => 'QueryMind',
            'support_email' => 'support@querymind.test',
            'maintenance_mode' => false,
        ] as $key => $value) {
            PlatformSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        Artisan::call('passport:keys', ['--force' => true]);

        if (!DB::table('oauth_clients')->where('grant_types', 'like', '%personal_access%')->exists()) {
            Artisan::call('passport:client', [
                '--personal' => true,
                '--name' => 'Sitebotai Personal Access Client',
                '--no-interaction' => true,
            ]);
        }
    }
}
