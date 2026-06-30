<?php

return [

    'default_review_frequency_days' => 180,

    'review_frequencies' => [
        ['days' => 30, 'label_en' => '30 Days', 'label_ar' => '30 يوماً'],
        ['days' => 90, 'label_en' => '90 Days', 'label_ar' => '90 يوماً'],
        ['days' => 180, 'label_en' => '180 Days', 'label_ar' => '180 يوماً'],
        ['days' => 365, 'label_en' => '365 Days', 'label_ar' => '365 يوماً'],
    ],

    'reminder_days_before' => 30,
    'reminder_days_overdue' => 30,

    'confidence_multipliers' => [
        'active' => 1.0,
        'needs_review' => 0.75,
        'deprecated' => 0.6,
    ],

    'send_email_reminders' => env('DOVA_KNOWLEDGE_EMAIL_REMINDERS', false),

];
