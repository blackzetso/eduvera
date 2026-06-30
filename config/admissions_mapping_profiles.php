<?php

return [
    'admissions_visit_v1' => [
        'version' => '1.0.0',
        'required' => [
            'contact.name',
            'applicant.first_name',
        ],
        'required_any' => [
            ['contact.phone', 'contact.email'],
        ],
        'transforms' => [
            'contact.name' => 'trim',
            'contact.phone' => 'normalize_phone',
            'contact.email' => 'lowercase_email',
            'applicant.first_name' => 'trim',
            'applicant.current_grade_label' => 'label_only',
            'visit.scheduled_date' => 'parse_date',
            'visit.scheduled_time' => 'parse_time',
            'visit.notes' => 'trim',
        ],
        'validators' => [
            [
                'rule' => 'h1_minimum_identity',
                'applies_to' => 'contact',
            ],
        ],
    ],
];
