<?php

return [
    'provider' => env('AI_PROVIDER', 'openai_compatible'),
    'api_key' => env('AI_API_KEY'),
    'base_url' => env('AI_BASE_URL', 'https://api.openai.com/v1'),
    'model' => env('AI_MODEL'),
    'connect_timeout' => (int) env('AI_CONNECT_TIMEOUT', 5),
    'timeout' => (int) env('AI_TIMEOUT', 30),
    'daily_user_limit' => (int) env('AI_DAILY_USER_LIMIT', 30),
    'daily_organization_limit' => (int) env('AI_DAILY_ORGANIZATION_LIMIT', 500),
    'max_question_characters' => (int) env('AI_MAX_QUESTION_CHARACTERS', 2000),
    'max_context_characters' => (int) env('AI_MAX_CONTEXT_CHARACTERS', 8000),
    'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 700),
    'max_teacher_draft_tokens' => (int) env('AI_MAX_TEACHER_DRAFT_TOKENS', 1400),
];
