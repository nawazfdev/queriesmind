<?php

namespace App\Services;

use App\Models\Document;
use App\Repositories\EmbeddingRepository;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class EmbeddingService
{
    public function __construct(
        protected EmbeddingRepository $embeddings,
        protected TextChunker $chunker,
        protected ChromaService $chroma,
        protected TenantContext $tenantContext,
    ) {
    }

    public function search(string $question, ?int $chatbotId = null, int $limit = 5): string
    {
        $tenant = $this->tenantContext->getTenant();

        if (! $tenant || ! $chatbotId) {
            return $this->embeddings->latestForChatbot($chatbotId, $limit)
                ->pluck('content_text')
                ->filter()
                ->implode("\n\n");
        }

        $results = collect();

        try {
            $results = collect($this->chroma->query($tenant->getKey(), $chatbotId, $question, $limit * 3))
                ->unique(fn (array $item) => data_get($item, 'id') ?: data_get($item, 'document'))
                ->take($limit);
        } catch (Throwable $exception) {
            report($exception);

            $results = collect($this->embeddings->latestForChatbot($chatbotId, $limit))
                ->map(fn ($item) => [
                    'id' => data_get($item, 'vector_reference') ?: "db_{$item->getKey()}",
                    'document' => (string) data_get($item, 'content_text'),
                ])
                ->unique(fn (array $item) => data_get($item, 'id') ?: data_get($item, 'document'))
                ->take($limit);
        }

        return $results->pluck('document')->filter()->implode("\n\n");
    }

    public function generateForDocument(Document $document): void
    {
        $content = $this->resolveContent($document);

        if ($content === '') {
            throw new RuntimeException('Document content is empty and could not be extracted.');
        }

        $chunks = $this->chunker->chunk($content);

        if ($chunks === []) {
            throw new RuntimeException('Document did not produce any chunks for indexing.');
        }

        $this->chroma->deleteDocumentChunks($document->tenant_id, $document->chatbot_id, $document->getKey());
        $indexed = $this->chroma->upsertDocumentChunks($document, $chunks);
        $this->embeddings->deleteForDocument($document->getKey());

        $rows = [];

        foreach ($indexed as $index => $item) {
            $rows[] = [
                'tenant_id' => $document->tenant_id,
                'chatbot_id' => $document->chatbot_id,
                'document_id' => $document->getKey(),
                'chunk_index' => $index,
                'vector_reference' => $item['id'],
                'source_url' => $document->source_url,
                'content_text' => $item['document'],
                'meta_json' => $item['metadata'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $this->embeddings->createMany($rows);
        $document->forceFill(['content' => $content])->save();
    }

    protected function resolveContent(Document $document): string
    {
        if (filled($document->content)) {
            return $this->normalizeUtf8((string) $document->content);
        }

        if (! ($document->source_type === 'document' && $document->file_path && Storage::disk('public')->exists($document->file_path))) {
            return '';
        }

        $extension = strtolower((string) pathinfo($document->file_path, PATHINFO_EXTENSION));

        if ($extension !== 'txt') {
            throw new RuntimeException("Unsupported document format '{$extension}'. Only TXT files are supported until PDF/DOCX extraction is added.");
        }

        return $this->normalizeUtf8((string) Storage::disk('public')->get($document->file_path));
    }

    protected function normalizeUtf8(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $normalized = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        if ($normalized === false) {
            throw new RuntimeException('Document content contains invalid text encoding.');
        }

        return trim($normalized);
    }
}

