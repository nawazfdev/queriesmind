<?php

namespace App\Repositories;

use App\Models\Embedding;
use Illuminate\Support\Collection;

class EmbeddingRepository
{
    public function latestForChatbot(?int $chatbotId = null, int $limit = 5): Collection
    {
        return Embedding::query()
            ->when($chatbotId, fn ($query) => $query->where('chatbot_id', $chatbotId))
            ->latest('id')
            ->limit($limit)
            ->get(['chatbot_id', 'document_id', 'vector_reference', 'content_text', 'meta_json']);
    }

    public function createMany(array $rows): void
    {
        $payload = array_map(function (array $row): array {
            if (array_key_exists('meta_json', $row) && is_array($row['meta_json'])) {
                $row['meta_json'] = json_encode($row['meta_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            return $row;
        }, $rows);

        Embedding::query()->insert($payload);
    }

    public function deleteForDocument(int $documentId): void
    {
        Embedding::query()->where('document_id', $documentId)->delete();
    }
}
