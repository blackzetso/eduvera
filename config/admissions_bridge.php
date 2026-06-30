<?php

return [
    'enabled' => (bool) env('ADMISSIONS_BRIDGE_ENABLED', false),

    'processing_mode' => env('ADMISSIONS_BRIDGE_PROCESSING_MODE', 'queue'),

    'queue_name' => env('ADMISSIONS_BRIDGE_QUEUE', 'admissions-bridge'),

    'dlq_enabled' => (bool) env('ADMISSIONS_BRIDGE_DLQ_ENABLED', true),

    'auto_disable_on_version_mismatch' => (bool) env('ADMISSIONS_BRIDGE_AUTO_DISABLE_ON_VERSION_MISMATCH', false),
];
