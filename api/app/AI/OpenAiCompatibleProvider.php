<?php

namespace App\AI;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiCompatibleProvider implements AiProvider
{
    public function complete(array $messages, int $maxOutputTokens): AiCompletion
    {
        $apiKey = (string) config('ai.api_key');
        $model = (string) config('ai.model');
        $baseUrl = rtrim((string) config('ai.base_url'), '/');
        if ($apiKey === '' || $model === '' || $baseUrl === '') {
            throw new RuntimeException('The AI provider is not configured.');
        }

        $response = Http::acceptJson()->withToken($apiKey)
            ->connectTimeout((int) config('ai.connect_timeout', 5))
            ->timeout((int) config('ai.timeout', 30))
            ->post($baseUrl.'/chat/completions', [
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => $maxOutputTokens,
                'temperature' => 0.3,
            ]);
        if (! $response->successful()) {
            throw new RuntimeException('The configured AI provider returned an error.');
        }

        $text = $response->json('choices.0.message.content');
        if (! is_string($text) || trim($text) === '') {
            throw new RuntimeException('The configured AI provider returned an empty answer.');
        }

        return new AiCompletion(
            text: trim($text),
            inputTokens: (int) $response->json('usage.prompt_tokens', 0),
            outputTokens: (int) $response->json('usage.completion_tokens', 0),
            model: (string) $response->json('model', $model),
        );
    }
}
