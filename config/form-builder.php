<?php

return [
    'version' => 2,

    'publication_statuses' => ['draft', 'published', 'archived'],

    'visibility_audiences' => [
        'public',
        'students',
        'parents',
        'teachers',
        'staff',
        'custom_roles',
    ],

    'visibility_user_types' => [
        'students' => 'student',
        'parents' => 'guardian',
        'teachers' => 'teacher',
    ],

    'permissions' => [
        'manage' => 'forms.manage',
        'view_submissions' => 'forms.submissions.view',
        'review_submissions' => 'forms.submissions.review',
    ],

    'rate_limits' => [
        'runtime_get' => 60,
        'submission_post' => 10,
        'submission_get' => 30,
        'submission_list' => 30,
        'status_patch' => 20,
    ],

    'supported_runtime_field_types' => [
        'text',
        'textarea',
        'number',
        'email',
        'phone',
        'date',
        'time',
        'url',
        'select',
        'multi_select',
        'radio',
        'checkbox',
    ],

    'field_type_groups' => [
        'basic' => ['text', 'textarea', 'number', 'email', 'phone', 'date', 'time', 'url'],
        'choice' => ['select', 'multi_select', 'radio', 'checkbox'],
        'advanced' => ['file', 'image', 'signature', 'rating', 'slider', 'color'],
        'education' => ['academic_year', 'grade', 'class', 'subject', 'teacher_selector'],
    ],

    'option_field_types' => [
        'select', 'multi_select', 'radio', 'checkbox',
    ],

    'system_templates' => [
        'student_admission',
        'employee_admission',
        'parent_registration',
        'teacher_application',
        'leave_request',
        'purchase_request',
        'maintenance_request',
        'visitor_registration',
        'complaint_form',
    ],

    'submission_statuses' => [
        'draft',
        'submitted',
        'under_review',
        'approved',
        'rejected',
    ],

    'submission_transitions' => [
        'draft' => ['draft', 'submitted'],
        'submitted' => ['under_review', 'approved', 'rejected'],
        'under_review' => ['approved', 'rejected', 'draft'],
        'approved' => [],
        'rejected' => [],
    ],

    'logic' => [
        'max_passes' => 10,
        'operators' => ['equals', 'not_equals', 'contains'],
        'actions' => ['show', 'hide', 'require', 'skip_section'],
    ],

    'validation' => [
        'rule_order' => [
            'min_length',
            'max_length',
            'min_value',
            'max_value',
            'regex',
            'email',
            'phone',
        ],
        'phone_pattern' => '/^\+?[0-9\s\-()]{7,20}$/',
        'messages' => [
            'required' => [
                'ar' => 'هذا الحقل مطلوب',
                'en' => 'This field is required',
            ],
            'min_length' => [
                'ar' => 'يجب ألا يقل عن :min أحرف',
                'en' => 'Must be at least :min characters',
            ],
            'max_length' => [
                'ar' => 'يجب ألا يزيد عن :max حرفاً',
                'en' => 'Must not exceed :max characters',
            ],
            'min_value' => [
                'ar' => 'يجب أن تكون القيمة :min على الأقل',
                'en' => 'Value must be at least :min',
            ],
            'max_value' => [
                'ar' => 'يجب ألا تتجاوز القيمة :max',
                'en' => 'Value must not exceed :max',
            ],
            'regex' => [
                'ar' => 'القيمة لا تطابق النمط المطلوب',
                'en' => 'Value does not match the required pattern',
            ],
            'email' => [
                'ar' => 'يرجى إدخال بريد إلكتروني صالح',
                'en' => 'Please enter a valid email address',
            ],
            'phone' => [
                'ar' => 'يرجى إدخال رقم هاتف صالح',
                'en' => 'Please enter a valid phone number',
            ],
        ],
    ],
];
