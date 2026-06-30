<?php

return [

    'enabled' => env('DOVA_AI_ENABLED', false),

    'api_key' => env('OPENAI_API_KEY'),

    'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),

    'model' => env('DOVA_AI_MODEL', 'gpt-4o-mini'),

    'temperature' => (float) env('DOVA_AI_TEMPERATURE', 0.3),

    'max_tokens' => (int) env('DOVA_AI_MAX_TOKENS', 1200),

    'timeout_seconds' => (int) env('DOVA_AI_TIMEOUT', 25),

    'debug' => env('DOVA_AI_DEBUG', env('DOVA_DEMO_MODE', false)),

    /*
    |--------------------------------------------------------------------------
    | Cost estimation (USD per 1M tokens) — used for analytics only
    |--------------------------------------------------------------------------
    */
    'pricing' => [
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
        'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
        'gpt-4-turbo' => ['input' => 10.00, 'output' => 30.00],
    ],

    'system_prompt' => <<<'PROMPT'
You are Dova, a friendly, professional, educational, helpful, and trustworthy AI school guide.

STRICT RULES:
- Use ONLY the knowledge and template content provided in the user message.
- NEVER invent, guess, or hallucinate school-specific facts (names, phones, emails, addresses, fees, policies, dates, programs).
- If the provided knowledge does not contain an answer, say clearly that the information is not available yet.
- Never add contact details, numbers, or school names that were not in the provided content.
- Respond in the requested language naturally — Arabic must feel native, not literally translated.
- Keep a warm, concise, premium educational tone.
- You may improve wording, flow, and helpfulness, but facts must remain identical to the source.

Return valid JSON only with keys: introduction, explanation, footer (all strings).
PROMPT,

    'no_knowledge_message' => [
        'en' => "I'm sorry, I couldn't find information about that yet.",
        'ar' => 'عذراً، لم أجد معلومات عن ذلك بعد.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Speech-to-text (Whisper fallback when browser STT unavailable)
    |--------------------------------------------------------------------------
    */
    'whisper_model' => env('DOVA_WHISPER_MODEL', 'whisper-1'),
    'whisper_max_file_mb' => (int) env('DOVA_WHISPER_MAX_FILE_MB', 10),
    'store_audio' => env('DOVA_STORE_VOICE_AUDIO', false),

];
