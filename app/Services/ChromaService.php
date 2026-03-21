<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class ChromaService
{
    public function collectionName(int $tenantId, ?int $chatbotId = null): string
    {
        return Str::lower(Str::limit("tenant_{$tenantId}_chatbot_".($chatbotId ?? 'global'), 120, ''));
    }

    public function upsertDocumentChunks(Document $document, array $chunks): array
    {
        $collectionName = $this->collectionName($document->tenant_id, $document->chatbot_id);
        $indexed = [];

        foreach ($chunks as $index => $chunk) {
            $id = $this->chunkId($document->getKey(), $index);
            $chunk = $this->normalizeUtf8($chunk);
            $metadata = [
                'tenant_id' => $document->tenant_id,
                'chatbot_id' => $document->chatbot_id,
                'source_document_id' => $document->getKey(),
                'chunk_index' => $index,
                'source_url' => $document->source_url,
                'source_type' => $document->source_type,
                'title' => $document->title,
            ];

            $response = $this->client()->post($this->addDocumentEndpoint(), [
                'id' => $id,
                'text' => $chunk,
                'collection_name' => $collectionName,
            ]);

            if ($response->failed()) {
                throw new RuntimeException('Chroma add_document request failed: '.$response->body());
            }

            $indexed[] = [
                'id' => $id,
                'metadata' => $metadata,
                'document' => $chunk,
            ];
        }

        return $indexed;
    }

    public function deleteDocumentChunks(int $tenantId, ?int $chatbotId, int $documentId): void
    {
        if (! config('services.chroma.enable_delete_endpoint', false)) {
            return;
        }

        $response = $this->client()->post($this->deleteDocumentEndpoint(), [
            'document_id' => $documentId,
            'collection_name' => $this->collectionName($tenantId, $chatbotId),
        ]);

        if ($response->status() === 404) {
            return;
        }

        if ($response->failed()) {
            throw new RuntimeException('Chroma delete_document request failed: '.$response->body());
        }
    }

    public function query(int $tenantId, ?int $chatbotId, string $query, int $limit = 5): array
    {
        $response = $this->client()->post($this->queryEndpoint(), [
            'query' => $this->normalizeUtf8($query),
            'top_k' => $limit,
            'collection_name' => $this->collectionName($tenantId, $chatbotId),
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Chroma query request failed: '.$response->body());
        }

        return collect($response->json('results', []))->values()->map(fn ($item) => [
            'id' => (string) data_get($item, 'id', ''),
            'document' => (string) data_get($item, 'text', ''),
            'metadata' => [],
            'distance' => data_get($item, 'distance'),
        ])->all();
    }

    public function health(): array
    {
        $response = $this->client()->get($this->healthEndpoint());

        if ($response->failed()) {
            throw new RuntimeException('Chroma health request failed: '.$response->body());
        }

        return $response->json();
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.chroma.host', 'http://127.0.0.1:8001'), '/'))
            ->timeout((int) config('services.chroma.timeout', 120))
            ->acceptJson()
            ->asJson();
    }

    protected function chunkId(int $documentId, int $chunkIndex): string
    {
        return "doc_{$documentId}_chunk_{$chunkIndex}";
    }

    protected function addDocumentEndpoint(): string
    {
        return '/'.ltrim((string) config('services.chroma.add_document_endpoint', 'add_document'), '/');
    }

    protected function queryEndpoint(): string
    {
        return '/'.ltrim((string) config('services.chroma.query_endpoint', 'query'), '/');
    }

    protected function deleteDocumentEndpoint(): string
    {
        return '/'.ltrim((string) config('services.chroma.delete_document_endpoint', 'delete_document'), '/');
    }

    protected function healthEndpoint(): string
    {
        return '/'.ltrim((string) config('services.chroma.health_endpoint', 'health'), '/');
    }

    protected function normalizeUtf8(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $normalized = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        if ($normalized === false) {
            throw new RuntimeException('Content contains invalid text encoding.');
        }

        return trim($normalized);
    }
}
