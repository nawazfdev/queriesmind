<?php

namespace App\Http\Controllers;

use App\Models\Chatbot;
use App\Models\ChatbotAppearance;
use App\Models\ChatbotEmbed;
use App\Models\ChatbotTrainingSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    public function index(): JsonResponse
    {
        $chatbots = Chatbot::query()
            ->withCount(['trainingSources', 'chats', 'documents'])
            ->with(['embed'])
            ->latest('id')
            ->get()
            ->map(fn (Chatbot $chatbot) => [
                'id' => $chatbot->id,
                'public_id' => $chatbot->public_id,
                'name' => $chatbot->name,
                'website_url' => $chatbot->website_url,
                'status' => $chatbot->status,
                'language' => $chatbot->language,
                'ai_model' => $chatbot->ai_model,
                'training_sources_count' => $chatbot->training_sources_count,
                'chats_count' => $chatbot->chats_count,
                'documents_count' => $chatbot->documents_count,
                'widget_key' => $chatbot->embed?->widget_key,
            ]);

        return response()->json(['data' => $chatbots]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('current_tenant');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'website_url' => ['nullable', 'url'],
            'language' => ['nullable', 'string', 'max:50'],
            'ai_model' => ['nullable', 'string', 'max:100'],
            'personality' => ['nullable', 'string', 'max:100'],
        ]);

        $chatbot = Chatbot::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'website_url' => $validated['website_url'] ?? null,
            'status' => 'active',
            'language' => $validated['language'] ?? 'english',
            'ai_model' => $validated['ai_model'] ?? 'gpt-4',
            'personality' => $validated['personality'] ?? 'Casual',
            'welcome_message' => 'Hi! How can I help you today?',
            'fallback_message' => 'I do not have enough information yet.',
            'temperature' => 0.7,
            'lead_capture_enabled' => false,
            'max_tokens' => 1000,
        ]);

        ChatbotAppearance::query()->create([
            'tenant_id' => $tenant->id,
            'chatbot_id' => $chatbot->id,
            'theme_color' => '#2563eb',
            'text_color' => '#0f172a',
            'position' => 'right',
            'border_radius' => '16px',
            'show_branding' => true,
        ]);

        ChatbotEmbed::query()->create([
            'tenant_id' => $tenant->id,
            'chatbot_id' => $chatbot->id,
            'widget_key' => 'qm_widget_'.bin2hex(random_bytes(12)),
            'allowed_domains' => $tenant->allowed_domains ?? [],
            'launcher_text' => 'Chat with us',
            'auto_open' => false,
        ]);

        return response()->json([
            'message' => 'Chatbot created successfully.',
            'data' => $chatbot->load(['appearance', 'embed']),
        ], 201);
    }

    public function show(Chatbot $chatbot): JsonResponse
    {
        $chatbot->load(['appearance', 'embed'])
            ->loadCount(['trainingSources', 'chats', 'documents']);

        return response()->json([
            'data' => [
                'chatbot' => $chatbot,
                'stats' => [
                    'training_sources_count' => $chatbot->training_sources_count,
                    'chats_count' => $chatbot->chats_count,
                    'documents_count' => $chatbot->documents_count,
                ],
            ],
        ]);
    }

    public function playground(Chatbot $chatbot): JsonResponse
    {
        $chatbot->loadCount(['trainingSources', 'documents']);

        return response()->json([
            'data' => [
                'chatbot' => $chatbot,
                'knowledge_base_status' => [
                    'knowledge_items' => $chatbot->documents_count,
                    'training_sources' => $chatbot->training_sources_count,
                    'ai_model' => $chatbot->ai_model,
                    'personality' => $chatbot->personality,
                ],
                'recent_chats' => $chatbot->chats()->latest()->take(10)->get(),
            ],
        ]);
    }

    public function training(Chatbot $chatbot): JsonResponse
    {
        return response()->json([
            'data' => [
                'chatbot' => $chatbot,
                'training_sources' => $chatbot->trainingSources()->latest()->get(),
                'documents' => $chatbot->documents()->latest()->get(),
            ],
        ]);
    }

    public function updateTraining(Request $request, Chatbot $chatbot): JsonResponse
    {
        $validated = $request->validate([
            'sources' => ['required', 'array'],
            'sources.*.source_type' => ['required', 'string'],
            'sources.*.title' => ['required', 'string'],
            'sources.*.source_reference' => ['required', 'string'],
            'sources.*.status' => ['nullable', 'string'],
            'sources.*.meta_json' => ['nullable', 'array'],
        ]);

        foreach ($validated['sources'] as $source) {
            ChatbotTrainingSource::query()->updateOrCreate(
                [
                    'chatbot_id' => $chatbot->id,
                    'source_type' => $source['source_type'],
                    'source_reference' => $source['source_reference'],
                ],
                [
                    'tenant_id' => $chatbot->tenant_id,
                    'title' => $source['title'],
                    'status' => $source['status'] ?? 'ready',
                    'meta_json' => $source['meta_json'] ?? [],
                    'last_trained_at' => now(),
                ]
            );
        }

        return response()->json([
            'message' => 'Training sources updated.',
            'data' => $chatbot->trainingSources()->latest()->get(),
        ]);
    }

    public function settings(Chatbot $chatbot): JsonResponse
    {
        return response()->json(['data' => $chatbot]);
    }

    public function updateSettings(Request $request, Chatbot $chatbot): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'website_url' => ['nullable', 'url'],
            'status' => ['sometimes', 'string'],
            'language' => ['sometimes', 'string', 'max:50'],
            'ai_model' => ['sometimes', 'string', 'max:100'],
            'personality' => ['sometimes', 'string', 'max:100'],
            'system_prompt' => ['nullable', 'string'],
            'welcome_message' => ['nullable', 'string'],
            'fallback_message' => ['nullable', 'string'],
            'temperature' => ['nullable', 'numeric', 'between:0,2'],
            'lead_capture_enabled' => ['nullable', 'boolean'],
            'max_tokens' => ['nullable', 'integer', 'min:100'],
        ]);

        $chatbot->update($validated);

        return response()->json([
            'message' => 'Chatbot settings updated.',
            'data' => $chatbot->fresh(),
        ]);
    }

    public function appearance(Chatbot $chatbot): JsonResponse
    {
        return response()->json([
            'data' => $chatbot->appearance,
        ]);
    }

    public function updateAppearance(Request $request, Chatbot $chatbot): JsonResponse
    {
        $validated = $request->validate([
            'theme_color' => ['nullable', 'string'],
            'text_color' => ['nullable', 'string'],
            'position' => ['nullable', 'string'],
            'border_radius' => ['nullable', 'string'],
            'avatar_url' => ['nullable', 'string'],
            'show_branding' => ['nullable', 'boolean'],
            'custom_css' => ['nullable', 'string'],
        ]);

        $appearance = $chatbot->appearance()->updateOrCreate(
            ['chatbot_id' => $chatbot->id],
            array_merge($validated, ['tenant_id' => $chatbot->tenant_id])
        );

        return response()->json([
            'message' => 'Appearance updated.',
            'data' => $appearance,
        ]);
    }

    public function embed(Chatbot $chatbot): JsonResponse
    {
        $embed = $chatbot->embed;

        return response()->json([
            'data' => [
                'config' => $embed,
                'snippet' => sprintf(
                    '<script src="%s/widget.js" data-widget-key="%s" data-chatbot-id="%s"></script>',
                    rtrim(config('app.url'), '/'),
                    $embed?->widget_key,
                    $chatbot->public_id
                ),
            ],
        ]);
    }

    public function updateEmbed(Request $request, Chatbot $chatbot): JsonResponse
    {
        $validated = $request->validate([
            'allowed_domains' => ['nullable', 'array'],
            'allowed_domains.*' => ['string'],
            'launcher_text' => ['nullable', 'string'],
            'auto_open' => ['nullable', 'boolean'],
            'bubble_icon' => ['nullable', 'string'],
        ]);

        $embed = $chatbot->embed()->updateOrCreate(
            ['chatbot_id' => $chatbot->id],
            array_merge($validated, ['tenant_id' => $chatbot->tenant_id])
        );

        return response()->json([
            'message' => 'Embed settings updated.',
            'data' => $embed,
        ]);
    }
}
