<?php

use App\Models\Document;
use App\Services\EmbeddingService;
use App\Services\TenantContext;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('querymind:migrate-to-chroma {--chatbot=} {--document=}', function (EmbeddingService $embeddings, TenantContext $tenantContext) {
    $query = Document::query()->withoutGlobalScopes()->orderBy('id');

    if ($chatbotId = $this->option('chatbot')) {
        $query->where('chatbot_id', $chatbotId);
    }

    if ($documentId = $this->option('document')) {
        $query->whereKey($documentId);
    }

    $documents = $query->get();
    $count = 0;

    foreach ($documents as $document) {
        $tenantContext->setTenant($document->tenant()->withoutGlobalScopes()->firstOrFail());
        $this->info("Indexing document {$document->id}...");
        $embeddings->generateForDocument($document);
        $document->update(['status' => 'processed']);
        $count++;
    }

    $this->info("Indexed {$count} document(s) into Chroma Cloud.");
})->purpose('Backfill existing documents into Chroma Cloud.');
