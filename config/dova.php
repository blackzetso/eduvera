<?php

return [

    'enabled' => env('DOVA_ENABLED', true),

    'demo_mode' => env('DOVA_DEMO_MODE', true),

    /*
    |--------------------------------------------------------------------------
    | Knowledge debug (shows source, record, confidence in API + widget)
    |--------------------------------------------------------------------------
    */
    'knowledge_debug' => env('DOVA_KNOWLEDGE_DEBUG', env('DOVA_DEMO_MODE', true)),

    'mode' => env('DOVA_MODE', 'copilot'),

    'max_suggested_actions' => (int) env('DOVA_MAX_SUGGESTED_ACTIONS', 4),

    'max_quick_actions' => (int) env('DOVA_MAX_QUICK_ACTIONS', 6),

    /*
    |--------------------------------------------------------------------------
    | Page context detection (URL pattern → context id)
    |--------------------------------------------------------------------------
    | First match wins. Used to boost relevant actions before AI integration.
    */
    'contexts' => [
        ['id' => 'attendance', 'patterns' => ['admin/attendances', 'teacher/attendances', 'guardian/students/*/attendance']],
        ['id' => 'students', 'patterns' => ['admin/students', 'guardian/students', 'student/']],
        ['id' => 'parents', 'patterns' => ['admin/parents']],
        ['id' => 'teachers', 'patterns' => ['admin/teachers', 'teacher/timetables']],
        ['id' => 'forms', 'patterns' => ['admin/forms', 'form/']],
        ['id' => 'website', 'patterns' => ['admin/website']],
        ['id' => 'timetable', 'patterns' => ['admin/timetable', 'teacher/timetables']],
        ['id' => 'live_streams', 'patterns' => ['admin/live-streams', 'teacher/live-streams', 'student/live-streams', 'join/']],
        ['id' => 'wallet', 'patterns' => ['guardian/wallet', 'admin/wallet']],
        ['id' => 'grades', 'patterns' => ['guardian/students/*/grades']],
        ['id' => 'admissions', 'patterns' => ['#visit', '#admissions']],
        ['id' => 'settings', 'patterns' => ['admin/settings']],
        ['id' => 'dashboard', 'patterns' => ['admin/dashboard', 'teacher/dashboard', 'guardian/dashboard', 'student/dashboard']],
    ],

    'context_labels' => [
        'home' => ['en' => 'Homepage', 'ar' => 'الصفحة الرئيسية'],
        'attendance' => ['en' => 'Attendance', 'ar' => 'الحضور'],
        'students' => ['en' => 'Students', 'ar' => 'الطلاب'],
        'parents' => ['en' => 'Parents', 'ar' => 'أولياء الأمور'],
        'teachers' => ['en' => 'Teachers', 'ar' => 'المعلمون'],
        'forms' => ['en' => 'Forms', 'ar' => 'النماذج'],
        'website' => ['en' => 'Website CMS', 'ar' => 'إدارة الموقع'],
        'timetable' => ['en' => 'Timetable', 'ar' => 'الجدول الدراسي'],
        'live_streams' => ['en' => 'Live Streams', 'ar' => 'البث المباشر'],
        'wallet' => ['en' => 'Fees & Wallet', 'ar' => 'الرسوم والمحفظة'],
        'grades' => ['en' => 'Grades', 'ar' => 'الدرجات'],
        'admissions' => ['en' => 'Admissions', 'ar' => 'القبول'],
        'settings' => ['en' => 'Settings', 'ar' => 'الإعدادات'],
        'dashboard' => ['en' => 'Dashboard', 'ar' => 'لوحة التحكم'],
    ],

    'role_labels' => [
        'guest' => ['en' => 'Visitor', 'ar' => 'زائر'],
        'admin' => ['en' => 'Administrator', 'ar' => 'مدير النظام'],
        'teacher' => ['en' => 'Teacher', 'ar' => 'معلم'],
        'guardian' => ['en' => 'Parent', 'ar' => 'ولي أمر'],
        'student' => ['en' => 'Student', 'ar' => 'طالب'],
        'control_staff' => ['en' => 'Control Staff', 'ar' => 'مراقبة'],
        'social_worker' => ['en' => 'Social Worker', 'ar' => 'أخصائي اجتماعي'],
        'nurse' => ['en' => 'Nurse', 'ar' => 'تمريض'],
        'department_head' => ['en' => 'Department Head', 'ar' => 'رئيس قسم'],
        'card_reader' => ['en' => 'Card Reader', 'ar' => 'قارئ البطاقات'],
    ],

    'portal_labels' => [
        'public' => ['en' => 'Public Website', 'ar' => 'الموقع العام'],
        'admin' => ['en' => 'Admin Panel', 'ar' => 'لوحة الإدارة'],
        'teacher' => ['en' => 'Teacher Portal', 'ar' => 'بوابة المعلم'],
        'guardian' => ['en' => 'Parent Portal', 'ar' => 'بوابة ولي الأمر'],
        'student' => ['en' => 'Student Portal', 'ar' => 'بوابة الطالب'],
    ],

];
