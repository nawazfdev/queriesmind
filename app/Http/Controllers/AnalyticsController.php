<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->attributes->get('current_tenant');

        $analytics = [
            'total_chatbots' => $tenant->chatbots()->count(),
            'total_chats' => $tenant->chats()->count(),
            'total_documents' => $tenant->documents()->count(),
            'total_embeddings' => $tenant->embeddings()->count(),
            'total_websites' => $tenant->websites()->count(),
            'total_training_sources' => $tenant->chatbotTrainingSources()->count(),
            'recent_chats' => $tenant->chats()->latest()->take(10)->get(),
        ];

        return response()->json($analytics);
    }
}
