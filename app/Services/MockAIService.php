<?php

namespace App\Services;

use App\Services\Contracts\AIServiceInterface;
use Illuminate\Support\Str;

class MockAIService implements AIServiceInterface
{
    public function generateAnswer(string $question, string $context, array $options = []): string
    {
        $contextSnippet = Str::limit(trim($context) ?: 'No indexed context available yet.', 400);

        return sprintf(
            "QueryMind answer: %s\n\nContext summary: %s",
            $question,
            $contextSnippet
        );
    }
}
