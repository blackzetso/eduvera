<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'bunny' => [
        // General Bunny API key (used by BunnyHttpClient)
        'api_key' => env('BUNNY_API_KEY'),
        // Stream-specific keys
        'stream_api_key' => env('BUNNY_STREAM_API_KEY'),
        'stream_library_id' => env('BUNNY_STREAM_LIBRARY_ID'),
        'stream_hostname' => env('BUNNY_STREAM_HOSTNAME'),
    ],

    'fawaterak' => [
        'api_key' => env('FAWATERAK_API_KEY'),
        'merchant_id' => env('FAWATERAK_MERCHANT_ID'),
        'base_url' => env('FAWATERAK_BASE_URL', 'https://staging.fawaterk.com/api/v2'),
    ],

    'exchange_rate' => [
        // Using exchangerate-api.com free tier (1,500 requests/month)
        // No API key required for free tier
        // API URL: https://open.er-api.com/v6/latest/{from}
        'api_url' => env('EXCHANGE_RATE_API_URL', 'https://open.er-api.com/v6/latest/{from}'),
        'api_key' => env('EXCHANGE_RATE_API_KEY', ''), // Optional for free tier
        'cache_duration' => env('EXCHANGE_RATE_CACHE_DURATION', 3600), // 1 hour
    ],

    'whatsapp' => [
        'api_url' => env('WHATSAPP_API_URL', 'http://localhost:3000'),
        'api_key' => env('WHATSAPP_API_KEY', ''),
        // Allow overriding endpoint path if your Baileys bridge differs
        'send_endpoint' => env('WHATSAPP_SEND_ENDPOINT', '/api/send-message'),
    ],

];
