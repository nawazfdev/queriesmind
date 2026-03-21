<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WebsiteCrawlerService
{
    public function crawl(string $startUrl): array
    {
        $maxPages = (int) config('querymind.crawler.max_pages', 25);
        $timeout = (int) config('querymind.crawler.timeout_seconds', 15);
        $origin = parse_url($startUrl);
        $host = $origin['host'] ?? null;

        if (! $host) {
            throw new \InvalidArgumentException('Invalid crawl URL.');
        }

        $normalizedStart = $this->normalizeUrl($startUrl);
        $queue = $normalizedStart ? [[$normalizedStart, 0]] : [];
        $seen = [];
        $pages = [];

        while ($queue !== [] && count($pages) < $maxPages) {
            [$url] = array_shift($queue);

            if (! $url || isset($seen[$url])) {
                continue;
            }

            $seen[$url] = true;
            $response = Http::timeout($timeout)
                ->accept('text/html,application/xhtml+xml')
                ->get($url);

            if (! $response->successful() || ! str_contains((string) $response->header('Content-Type'), 'html')) {
                continue;
            }

            $parsed = $this->extractPage($url, $response->body());

            if (($parsed['content'] ?? '') === '') {
                continue;
            }

            $pages[] = $parsed;

            foreach ($parsed['links'] as $link) {
                $normalized = $this->normalizeUrl($link, $url);

                if (! $normalized) {
                    continue;
                }

                $linkHost = parse_url($normalized, PHP_URL_HOST);

                if ($linkHost !== $host || isset($seen[$normalized])) {
                    continue;
                }

                $queue[] = [$normalized, 0];
            }
        }

        $combined = collect($pages)
            ->map(function (array $page): string {
                $sections = array_filter([
                    $page['title'] ? 'Page Title: '.$page['title'] : null,
                    'Page URL: '.$page['url'],
                    $page['content'],
                    $page['link_content'] !== '' ? "Links:\n".$page['link_content'] : null,
                ]);

                return trim(implode("\n\n", $sections));
            })
            ->filter()
            ->implode("\n\n---\n\n");

        return [
            'title' => $pages[0]['title'] ?? parse_url($startUrl, PHP_URL_HOST),
            'content' => $combined,
            'pages' => $pages,
        ];
    }

    protected function extractPage(string $url, string $html): array
    {
        libxml_use_internal_errors(true);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);

        foreach (['//script', '//style', '//noscript', '//svg', '//footer'] as $query) {
            foreach ($xpath->query($query) ?: [] as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $title = trim((string) ($xpath->query('//title')->item(0)?->textContent ?? ''));
        $bodyText = trim(preg_replace('/\s+/u', ' ', $dom->textContent ?? '') ?? '');
        $bodyText = Str::squish(html_entity_decode($bodyText));
        $links = [];
        $linkLines = [];

        foreach ($xpath->query('//a[@href]') ?: [] as $node) {
            $href = trim((string) $node->attributes?->getNamedItem('href')?->nodeValue);
            $text = Str::squish(trim((string) $node->textContent));

            if ($href === '') {
                continue;
            }

            $links[] = $href;
            $linkLines[] = $text !== '' ? $text.' -> '.$href : $href;
        }

        return [
            'url' => $url,
            'title' => $title,
            'content' => $bodyText,
            'link_content' => implode("\n", array_values(array_unique($linkLines))),
            'links' => array_values(array_unique($links)),
        ];
    }

    protected function normalizeUrl(string $url, ?string $baseUrl = null): ?string
    {
        $url = trim($url);

        if ($url === '' || str_starts_with($url, 'mailto:') || str_starts_with($url, 'tel:') || str_starts_with($url, 'javascript:')) {
            return null;
        }

        if ($baseUrl && ! preg_match('/^https?:\/\//i', $url)) {
            $base = parse_url($baseUrl);

            if (! $base || ! isset($base['scheme'], $base['host'])) {
                return null;
            }

            if (str_starts_with($url, '/')) {
                $url = $base['scheme'].'://'.$base['host'].$url;
            } else {
                $path = isset($base['path']) ? preg_replace('#/[^/]*$#', '/', $base['path']) : '/';
                $url = $base['scheme'].'://'.$base['host'].($path ?: '/').$url;
            }
        }

        $parts = parse_url($url);

        if (! ($parts['scheme'] ?? null) || ! ($parts['host'] ?? null)) {
            return null;
        }

        $normalized = strtolower($parts['scheme']).'://'.strtolower($parts['host']);

        if (isset($parts['port'])) {
            $normalized .= ':'.$parts['port'];
        }

        $normalized .= $parts['path'] ?? '/';

        if (($parts['query'] ?? '') !== '') {
            $normalized .= '?'.$parts['query'];
        }

        return rtrim(strtok($normalized, '#') ?: $normalized, '/');
    }
}
