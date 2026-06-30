<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Teacher absence statuses (unavailable for coverage)
    |--------------------------------------------------------------------------
    */
    'teacher_unavailable_statuses' => [
        'absent',
        'sick_leave',
        'emergency_leave',
        'unpaid_leave',
        'vacation',
    ],

    'teacher_attendance_statuses' => [
        'present' => 'حاضر',
        'absent' => 'غائب',
        'sick_leave' => 'إجازة مرضية',
        'emergency_leave' => 'إجازة طارئة',
        'unpaid_leave' => 'إجازة بدون راتب',
        'vacation' => 'إجازة',
        'late' => 'متأخر',
    ],

    'arabic_weekdays' => [
        'Sunday' => 'الأحد',
        'Monday' => 'الإثنين',
        'Tuesday' => 'الثلاثاء',
        'Wednesday' => 'الأربعاء',
        'Thursday' => 'الخميس',
        'Friday' => 'الجمعة',
        'Saturday' => 'السبت',
    ],

    'daily_coverage' => [
        'default_max_weekly_load' => 24,
        'max_daily_substitute_periods' => 6,
        'school_day_end_hour' => 16,
        'max_daily_swaps_per_teacher' => 4,
        'swap_types' => [
            'move_lesson' => 'نقل حصة',
            'swap_lessons' => 'تبديل حصتين',
            'replace_teacher' => 'استبدال معلم',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | أولويات اقتراح بديل الغياب (قابلة للتعديل من لوحة الإدارة)
    |--------------------------------------------------------------------------
    */
    'coverage_priority_defaults' => [
        'balance_penalty_per_point' => 4,
        'week_penalty_per_coverage' => 5,
        'rules' => [
            'same_subject' => [
                'enabled' => true,
                'weight' => 100,
                'label' => 'نفس المادة / التخصص',
            ],
            'same_department' => [
                'enabled' => true,
                'weight' => 80,
                'label' => 'نفس القسم',
            ],
            'same_stage' => [
                'enabled' => true,
                'weight' => 80,
                'label' => 'نفس المرحلة الدراسية',
            ],
            'same_grade' => [
                'enabled' => true,
                'weight' => 50,
                'label' => 'نفس الصف / الفصل',
            ],
            'free_period' => [
                'enabled' => true,
                'weight' => 100,
                'label' => 'متفرغ في نفس الحصة',
            ],
            'low_coverage_balance' => [
                'enabled' => true,
                'weight' => 40,
                'label' => 'رصيد تغطية أقل (عدالة)',
            ],
            'department_head' => [
                'enabled' => true,
                'weight' => 15,
                'label' => 'رئيس قسم / خبرة',
            ],
        ],
    ],

];
