<?php

namespace App\Http\Controllers;

use App\Jobs\CrawlWebsiteJob;
use App\Models\Chatbot;
use App\Models\ChatbotTrainingSource;
use Illuminate\Http\Request;

class WebsiteController extends Controller
{
    public function add(Request $request)
    {
        $validated = $request->validate([
            'chatbot_id' => 'required|integer',
            'url' => 'required|url',
            'name' => 'required|string',
        ]);

        $tenant = $request->attributes->get('current_tenant');
        Chatbot::query()->findOrFail($validated['chatbot_id']);

        ChatbotTrainingSource::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'chatbot_id' => $validated['chatbot_id'],
                'source_type' => 'website',
                'source_reference' => $validated['url'],
            ],
            [
                'title' => $validated['name'],
                'status' => 'processing',
                'meta_json' => null,
            ]
        );

        CrawlWebsiteJob::dispatch($tenant->id, $validated['chatbot_id'], $validated['url']);

        return response()->json([
            'data' => [
                'url' => $validated['url'],
                'status' => 'queued',
            ],
        ]);
    }
}
