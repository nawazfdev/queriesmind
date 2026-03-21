<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateEmbeddingJob;
use App\Models\Chatbot;
use App\Models\ChatbotTrainingSource;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'chatbot_id' => 'required|integer',
            'file' => 'required|file|mimes:pdf,docx,txt|max:10240',
        ]);

        $tenant = $request->attributes->get('current_tenant');
        Chatbot::query()->findOrFail($validated['chatbot_id']);

        $file = $request->file('file');
        $path = $file->store('documents', 'public');

        $document = Document::create([
            'tenant_id' => $tenant->id,
            'chatbot_id' => $validated['chatbot_id'],
            'title' => $file->getClientOriginalName(),
            'source_type' => 'document',
            'source_url' => $path,
            'file_path' => $path,
            'status' => 'uploaded',
        ]);

        $this->upsertTrainingSource(
            tenantId: $tenant->id,
            chatbotId: $validated['chatbot_id'],
            sourceType: 'document',
            sourceReference: $path,
            title: $file->getClientOriginalName(),
        );

        GenerateEmbeddingJob::dispatch($document->id);

        return response()->json([
            'data' => [
                'id' => $document->id,
                'file_path' => $document->file_path,
                'status' => $document->status,
            ],
        ]);
    }

    public function storeText(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'chatbot_id' => 'required|integer',
            'title' => 'required|string|max:255',
            'content' => 'required|string|min:1',
        ]);

        $tenant = $request->attributes->get('current_tenant');
        Chatbot::query()->findOrFail($validated['chatbot_id']);

        $reference = 'text:'.sha1($tenant->id.'|'.$validated['chatbot_id'].'|'.$validated['title'].'|'.$validated['content']);

        $document = Document::create([
            'tenant_id' => $tenant->id,
            'chatbot_id' => $validated['chatbot_id'],
            'title' => $validated['title'],
            'source_type' => 'text',
            'source_url' => $reference,
            'file_path' => $reference,
            'content' => trim($validated['content']),
            'status' => 'uploaded',
        ]);

        $this->upsertTrainingSource(
            tenantId: $tenant->id,
            chatbotId: $validated['chatbot_id'],
            sourceType: 'text',
            sourceReference: $reference,
            title: $validated['title'],
        );

        GenerateEmbeddingJob::dispatch($document->id);

        return response()->json([
            'data' => [
                'id' => $document->id,
                'source_type' => $document->source_type,
                'status' => $document->status,
            ],
        ]);
    }

    protected function upsertTrainingSource(int $tenantId, int $chatbotId, string $sourceType, string $sourceReference, string $title): void
    {
        ChatbotTrainingSource::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'chatbot_id' => $chatbotId,
                'source_type' => $sourceType,
                'source_reference' => $sourceReference,
            ],
            [
                'title' => $title,
                'status' => 'processing',
            ]
        );
    }
}
