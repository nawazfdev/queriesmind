<?php

namespace App\Services;

use App\Services\Contracts\AIServiceInterface;

class OpenRouterAIService implements AIServiceInterface
{
    public function __construct(protected OpenRouterService $openRouter)
    {
    }

    public function generateAnswer(string $question, string $context, array $options = []): string
    {
        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a website support chatbot. Answer using only the supplied context when possible. If context is missing, say that clearly and keep the answer short.',
            ],
            [
                'role' => 'user',
                'content' => "Context:\n{$context}\n\nQuestion:\n{$question}",
            ],
        ];

        $answer = $this->openRouter->chat($messages, [
            'temperature' => 0.2,
            'max_tokens' => $options['max_tokens'] ?? 700,
            'model' => $options['model'] ?? config('services.openrouter.chat_model'),
        ]);

        return $answer !== '' ? $answer : 'I could not generate an answer from the current knowledge base.';
    }
}
