<?php

namespace App\Jobs;

use App\Models\ChatbotTrainingSource;
use App\Models\Document;
use App\Models\Website;
use App\Services\EmbeddingService;
use App\Services\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateEmbeddingJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $documentId)
    {
    }

    public function handle(EmbeddingService $embeddings, TenantContext $tenantContext): void
    {
        $document = Document::query()->withoutGlobalScopes()->findOrFail($this->documentId);
        $tenantContext->setTenant($document->tenant()->withoutGlobalScopes()->firstOrFail());

        $document->update(['status' => 'processing']);

        try {
            $embeddings->generateForDocument($document);
            $document->update(['status' => 'processed']);

            if ($document->source_url) {
                ChatbotTrainingSource::query()
                    ->where('chatbot_id', $document->chatbot_id)
                    ->where('source_reference', $document->source_url)
                    ->update([
                        'status' => 'ready',
                        'last_trained_at' => now(),
                    ]);
            }

            if ($document->source_type === 'website' && $document->source_url) {
                Website::query()
                    ->where('chatbot_id', $document->chatbot_id)
                    ->where('url', $document->source_url)
                    ->update(['status' => 'crawled']);
            }
        } catch (\Throwable $e) {
            $document->update(['status' => 'failed']);

            if ($document->source_url) {
                ChatbotTrainingSource::query()
                    ->where('chatbot_id', $document->chatbot_id)
                    ->where('source_reference', $document->source_url)
                    ->update(['status' => 'failed']);
            }

            throw $e;
        }
    }
}
