<?php

namespace App\Http\Controllers;

use App\Models\Chatbot;
use App\Services\ChatService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(protected ChatService $chatService)
    {
    }

    public function store(Request $request)
    {
        $payload = $request->validate([
            'chatbot_id' => 'required|string',
            'question' => 'required|string',
            'session_id' => 'nullable|string',
            'api_key' => 'nullable|string',
        ]);

        $tenant = $request->attributes->get('current_tenant');
        $chatbot = Chatbot::query()
            ->where('id', $payload['chatbot_id'])
            ->orWhere('public_id', $payload['chatbot_id'])
            ->firstOrFail();

        $payload['chatbot_id'] = $chatbot->id;
        $result = $this->chatService->handle($tenant, $payload, $request->user()?->id);

        return response()->json([
            'data' => $result,
        ]);
    }
}
