<?php

namespace App\AI;

interface AiProvider
{
    /** @param array<int, array{role:string,content:string}> $messages */
    public function complete(array $messages, int $maxOutputTokens): AiCompletion;
}
