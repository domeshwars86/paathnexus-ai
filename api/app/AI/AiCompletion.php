<?php

namespace App\AI;

final readonly class AiCompletion
{
    public function __construct(
        public string $text,
        public int $inputTokens,
        public int $outputTokens,
        public string $model,
    ) {}
}
