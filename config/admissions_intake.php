<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rate limiting (requests per minute per IP)
    |--------------------------------------------------------------------------
    */
    'rate_limit_per_minute' => (int) env('ADMISSIONS_INTAKE_RATE_LIMIT', 10),

    /*
    |--------------------------------------------------------------------------
    | CAPTCHA — disabled by default; school enables via env
    |--------------------------------------------------------------------------
    */
    'captcha' => [
        'enabled' => (bool) env('ADMISSIONS_INTAKE_CAPTCHA_ENABLED', false),
        'provider' => env('ADMISSIONS_INTAKE_CAPTCHA_PROVIDER', 'recaptcha'),
        'secret_key' => env('ADMISSIONS_INTAKE_CAPTCHA_SECRET'),
        'site_key' => env('ADMISSIONS_INTAKE_CAPTCHA_SITE_KEY'),
        'field' => 'captcha_token',
    ],

    /*
    |--------------------------------------------------------------------------
    | Abuse / spam detection
    |--------------------------------------------------------------------------
    */
    'spam' => [
        'enabled' => (bool) env('ADMISSIONS_INTAKE_SPAM_DETECTION', true),
        'duplicate_window_minutes' => (int) env('ADMISSIONS_INTAKE_DUPLICATE_WINDOW', 30),
        'max_same_email_per_window' => (int) env('ADMISSIONS_INTAKE_MAX_SAME_EMAIL', 3),
        'max_same_phone_per_window' => (int) env('ADMISSIONS_INTAKE_MAX_SAME_PHONE', 3),
        'honeypot_field' => '_hp_url',
    ],

    /*
    |--------------------------------------------------------------------------
    | Request logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => (bool) env('ADMISSIONS_INTAKE_LOGGING', true),
        'log_rejected' => true,
        'log_success' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Document uploads (admissions workspace)
    |--------------------------------------------------------------------------
    */
    'documents' => [
        'max_size_kb' => (int) env('ADMISSIONS_DOCUMENT_MAX_KB', 10240),
        'allowed_mimes' => [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
        'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx'],
        'disk' => env('ADMISSIONS_DOCUMENT_DISK', 'local'),
        'path_prefix' => 'admissions/documents',
    ],
];
