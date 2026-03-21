<?php

namespace App\Jobs;

use App\Models\ChatbotTrainingSource;
use App\Models\Document;
use App\Models\Tenant;
use App\Models\Website;
use App\Services\TenantContext;
use App\Services\WebsiteCrawlerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CrawlWebsiteJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $tenantId,
        public int $chatbotId,
        public string $url,
    ) {
    }

    public function handle(TenantContext $tenantContext, WebsiteCrawlerService $crawler): void
    {
        $tenant = Tenant::query()->findOrFail($this->tenantId);
        $tenantContext->setTenant($tenant);

        $website = Website::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->getKey(),
                'chatbot_id' => $this->chatbotId,
                'url' => $this->url,
            ],
            [
                'name' => parse_url($this->url, PHP_URL_HOST) ?: $this->url,
                'status' => 'crawling',
                'content' => null,
            ]
        );

        $source = ChatbotTrainingSource::query()->where('chatbot_id', $this->chatbotId)
            ->where('source_type', 'website')
            ->where('source_reference', $this->url)
            ->latest('id')
            ->first();

        try {
            $crawl = $crawler->crawl($this->url);

            $website->update([
                'name' => $crawl['title'] ?: $website->name,
                'status' => 'crawled',
                'content' => $crawl['content'],
            ]);

            $document = Document::query()->create([
                'tenant_id' => $tenant->getKey(),
                'chatbot_id' => $this->chatbotId,
                'title' => $crawl['title'] ?: $website->name,
                'source_type' => 'website',
                'source_url' => $this->url,
                'file_path' => $this->url,
                'content' => $crawl['content'],
                'meta_json' => [
                    'page_count' => count($crawl['pages']),
                    'pages' => collect($crawl['pages'])->pluck('url')->values()->all(),
                ],
                'status' => 'processing',
            ]);

            $source?->update([
                'title' => $crawl['title'] ?: $source->title,
                'status' => 'processing',
                'meta_json' => [
                    'page_count' => count($crawl['pages']),
                    'pages' => collect($crawl['pages'])->pluck('url')->values()->all(),
                ],
            ]);

            GenerateEmbeddingJob::dispatch($document->id);
        } catch (\Throwable $e) {
            $website->update(['status' => 'failed']);
            $source?->update(['status' => 'failed']);
            throw $e;
        }
    }
}
