<?php

return [

    'enabled' => env('TRANSLATION_AUTO_ENABLED', true),

    'default' => env('TRANSLATION_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Synchronous translation size limit (characters)
    |--------------------------------------------------------------------------
    | Payloads above this total character count use the queue when possible.
    | Admin form saves still attempt synchronous translation first with timeout.
    */
    'sync_max_characters' => (int) env('TRANSLATION_SYNC_MAX_CHARACTERS', 4000),

    'timeout_seconds' => (int) env('TRANSLATION_TIMEOUT_SECONDS', 30),

    'source_locale' => 'en',
    'target_locale' => 'ar',

    'suffix' => [
        'arabic' => '_ar',
        'english' => '_en',
    ],

    /*
    |--------------------------------------------------------------------------
    | Fields never auto-translated (exact names, any nesting level)
    |--------------------------------------------------------------------------
    */
    'skip_fields' => [
        'slug', 'href', 'url', 'uri', 'path', 'email', 'phone', 'whatsapp',
        'image_src', 'image_alt', 'photo_src', 'photo_alt', 'icon',
        'published_at', 'date', 'date_short', 'sort_order', 'external_id',
        'type', 'variant', 'id', 'uuid', 'disk', 'filename', 'mime_type',
        'mapEmbedUrl', 'map_embed_url', 'logo_path', 'favicon_path',
        'primary_color', 'font_family', 'youtube', 'facebook', 'instagram',
        'linkedin', 'twitter', 'video_url', 'embed_url',
    ],

    'providers' => [

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model' => env('OPENAI_TRANSLATION_MODEL', 'gpt-4o-mini'),
            'temperature' => (float) env('OPENAI_TRANSLATION_TEMPERATURE', 0.2),
        ],

        'deepl' => [
            'api_key' => env('DEEPL_API_KEY'),
            'base_url' => env('DEEPL_BASE_URL', 'https://api-free.deepl.com/v2'),
        ],

        'google' => [
            'api_key' => env('GOOGLE_TRANSLATE_API_KEY'),
            'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
        ],

    ],

    'system_prompt' => <<<'PROMPT'
You are a professional translator for an international school website in Egypt.
Translate naturally and professionally. Preserve exactly:
- School and company names
- Proper nouns and abbreviations
- Phone numbers, emails, URLs
- HTML tags and attributes
- Placeholders like {year}, {school_name}, %s, :name
- Markdown formatting
Do not add explanations. Return only the translated text.
PROMPT,

];
