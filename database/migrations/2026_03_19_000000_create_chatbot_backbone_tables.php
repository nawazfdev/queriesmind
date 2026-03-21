<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('website_url')->nullable();
            $table->string('status')->default('active');
            $table->string('language')->default('english');
            $table->string('ai_model')->default('gpt-4');
            $table->string('personality')->default('Casual');
            $table->text('system_prompt')->nullable();
            $table->text('welcome_message')->nullable();
            $table->text('fallback_message')->nullable();
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->boolean('lead_capture_enabled')->default(false);
            $table->unsignedInteger('max_tokens')->default(1000);
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('chatbot_appearances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('chatbot_id')->constrained('chatbots')->cascadeOnDelete();
            $table->string('theme_color')->default('#2563eb');
            $table->string('text_color')->default('#0f172a');
            $table->string('position')->default('right');
            $table->string('border_radius')->default('16px');
            $table->string('avatar_url')->nullable();
            $table->boolean('show_branding')->default(true);
            $table->text('custom_css')->nullable();
            $table->timestamps();

            $table->unique('chatbot_id');
        });

        Schema::create('chatbot_embeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('chatbot_id')->constrained('chatbots')->cascadeOnDelete();
            $table->string('widget_key')->unique();
            $table->json('allowed_domains')->nullable();
            $table->string('launcher_text')->default('Chat with us');
            $table->boolean('auto_open')->default(false);
            $table->string('bubble_icon')->nullable();
            $table->timestamps();

            $table->unique('chatbot_id');
        });

        Schema::create('chatbot_training_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('chatbot_id')->constrained('chatbots')->cascadeOnDelete();
            $table->string('source_type');
            $table->string('title');
            $table->text('source_reference');
            $table->string('status')->default('ready');
            $table->json('meta_json')->nullable();
            $table->timestamp('last_trained_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'chatbot_id', 'source_type']);
        });

        Schema::table('chats', function (Blueprint $table) {
            $table->foreignId('chatbot_id')->nullable()->after('tenant_id')->constrained('chatbots')->nullOnDelete();
            $table->index(['tenant_id', 'chatbot_id']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('chatbot_id')->nullable()->after('tenant_id')->constrained('chatbots')->nullOnDelete();
            $table->index(['tenant_id', 'chatbot_id']);
        });

        Schema::table('embeddings', function (Blueprint $table) {
            $table->foreignId('chatbot_id')->nullable()->after('tenant_id')->constrained('chatbots')->nullOnDelete();
            $table->index(['tenant_id', 'chatbot_id']);
        });

        Schema::table('websites', function (Blueprint $table) {
            $table->foreignId('chatbot_id')->nullable()->after('tenant_id')->constrained('chatbots')->nullOnDelete();
            $table->index(['tenant_id', 'chatbot_id']);
        });
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table) {
            $table->dropForeign(['chatbot_id']);
            $table->dropColumn('chatbot_id');
        });

        Schema::table('embeddings', function (Blueprint $table) {
            $table->dropForeign(['chatbot_id']);
            $table->dropColumn('chatbot_id');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['chatbot_id']);
            $table->dropColumn('chatbot_id');
        });

        Schema::table('chats', function (Blueprint $table) {
            $table->dropForeign(['chatbot_id']);
            $table->dropColumn('chatbot_id');
        });

        Schema::dropIfExists('chatbot_training_sources');
        Schema::dropIfExists('chatbot_embeds');
        Schema::dropIfExists('chatbot_appearances');
        Schema::dropIfExists('chatbots');
    }
};
