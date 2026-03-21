<?php

namespace App\Services\Contracts;

interface AIServiceInterface
{
    public function generateAnswer(string $question, string $context, array $options = []): string;
}
