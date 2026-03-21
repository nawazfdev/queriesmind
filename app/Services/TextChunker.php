<?php

namespace App\Services;

class TextChunker
{
    public function chunk(string $text, ?int $targetSize = null, ?int $maxSize = null, ?int $overlap = null): array
    {
        $targetSize ??= (int) config('querymind.chunking.target_size', 850);
        $maxSize ??= (int) config('querymind.chunking.max_size', 1000);
        $overlap ??= (int) config('querymind.chunking.overlap', 120);

        $normalized = preg_replace('/\R{2,}/', "\n\n", preg_replace('/[ \t]+/', ' ', trim($text)) ?? '');

        if (! $normalized) {
            return [];
        }

        $paragraphs = preg_split('/\n{2,}/', $normalized) ?: [];
        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            if (mb_strlen($paragraph) > $maxSize) {
                foreach ($this->sliceLongParagraph($paragraph, $targetSize, $maxSize, $overlap) as $slice) {
                    if ($current !== '') {
                        $chunks[] = trim($current);
                        $current = '';
                    }

                    $chunks[] = $slice;
                }

                continue;
            }

            $candidate = trim($current === '' ? $paragraph : $current."\n\n".$paragraph);

            if (mb_strlen($candidate) <= $maxSize) {
                $current = $candidate;

                continue;
            }

            if ($current !== '') {
                $chunks[] = trim($current);
            }

            $current = $paragraph;
        }

        if ($current !== '') {
            $chunks[] = trim($current);
        }

        return array_values(array_filter($chunks, fn (string $chunk) => mb_strlen($chunk) >= 40));
    }

    protected function sliceLongParagraph(string $text, int $targetSize, int $maxSize, int $overlap): array
    {
        $chunks = [];
        $offset = 0;
        $length = mb_strlen($text);

        while ($offset < $length) {
            $sliceLength = min($maxSize, $length - $offset);
            $slice = mb_substr($text, $offset, $sliceLength);

            if ($sliceLength === $maxSize && $offset + $sliceLength < $length) {
                $lastBreakpoint = max(
                    (int) mb_strrpos($slice, '. '),
                    (int) mb_strrpos($slice, '! '),
                    (int) mb_strrpos($slice, '? '),
                    (int) mb_strrpos($slice, ' ')
                );

                if ($lastBreakpoint > (int) floor($targetSize * 0.7)) {
                    $slice = trim(mb_substr($slice, 0, $lastBreakpoint + 1));
                }
            }

            $slice = trim($slice);

            if ($slice !== '') {
                $chunks[] = $slice;
            }

            $offset += max(1, mb_strlen($slice) - $overlap);
        }

        return $chunks;
    }
}
