<?php

return [

    'sources' => [
        'school_info' => [
            'name_en' => 'School Information',
            'name_ar' => 'معلومات المدرسة',
            'sync_group' => 'school_info',
        ],
        'admissions' => [
            'name_en' => 'Admissions',
            'name_ar' => 'القبول',
            'sync_group' => 'admissions',
        ],
        'faq' => [
            'name_en' => 'FAQ',
            'name_ar' => 'الأسئلة الشائعة',
            'sync_group' => 'faq',
        ],
        'news' => [
            'name_en' => 'News',
            'name_ar' => 'الأخبار',
            'sync_group' => 'cms',
        ],
        'events' => [
            'name_en' => 'Events',
            'name_ar' => 'الفعاليات',
            'sync_group' => 'cms',
        ],
        'policies' => [
            'name_en' => 'Policies',
            'name_ar' => 'السياسات',
            'sync_group' => 'admissions',
        ],
        'hero' => [
            'name_en' => 'Hero Content',
            'name_ar' => 'قسم البطل',
            'sync_group' => 'website',
        ],
        'navigation' => [
            'name_en' => 'Navigation Labels',
            'name_ar' => 'روابط التنقل',
            'sync_group' => 'website',
        ],
        'academic_programs' => [
            'name_en' => 'Academic Programs',
            'name_ar' => 'البرامج الدراسية',
            'sync_group' => 'cms',
        ],
        'contact' => [
            'name_en' => 'Contact Information',
            'name_ar' => 'معلومات التواصل',
            'sync_group' => 'school_info',
        ],
    ],

    'sync_groups' => [
        'cms' => ['school_info', 'admissions', 'faq', 'news', 'events', 'policies', 'hero', 'navigation', 'academic_programs', 'contact'],
        'website' => ['school_info', 'hero', 'navigation', 'contact'],
        'faq' => ['faq'],
        'school_info' => ['school_info', 'contact'],
        'admissions' => ['admissions', 'policies'],
    ],

    'allowed_roles' => ['super_admin', 'admin', 'content_manager'],

    'faq_categories' => [
        ['slug' => 'admissions', 'name_en' => 'Admissions', 'name_ar' => 'القبول'],
        ['slug' => 'students', 'name_en' => 'Students', 'name_ar' => 'الطلاب'],
        ['slug' => 'finance', 'name_en' => 'Finance', 'name_ar' => 'المالية'],
        ['slug' => 'fees', 'name_en' => 'Fees', 'name_ar' => 'الرسوم'],
        ['slug' => 'academic', 'name_en' => 'Academic', 'name_ar' => 'الأكاديمي'],
        ['slug' => 'programs', 'name_en' => 'Programs', 'name_ar' => 'البرامج'],
        ['slug' => 'hr', 'name_en' => 'HR', 'name_ar' => 'الموارد البشرية'],
        ['slug' => 'uniform', 'name_en' => 'Uniform', 'name_ar' => 'الزي المدرسي'],
        ['slug' => 'transportation', 'name_en' => 'Transportation', 'name_ar' => 'المواصلات'],
        ['slug' => 'attendance', 'name_en' => 'Attendance', 'name_ar' => 'الحضور'],
        ['slug' => 'policies', 'name_en' => 'Policies', 'name_ar' => 'السياسات'],
        ['slug' => 'student_life', 'name_en' => 'Student Life', 'name_ar' => 'حياة الطلاب'],
        ['slug' => 'parents', 'name_en' => 'Parents', 'name_ar' => 'أولياء الأمور'],
        ['slug' => 'teachers', 'name_en' => 'Teachers', 'name_ar' => 'المدرسون'],
        ['slug' => 'general', 'name_en' => 'General', 'name_ar' => 'عام'],
    ],

    'gap_priorities' => [
        'high' => 30,
        'medium' => 10,
        'low' => 3,
    ],

];
