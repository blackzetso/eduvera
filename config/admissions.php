<?php

return [
    'reference_prefix' => 'ADM',

    'academic_year_start_month' => (int) env('ADMISSIONS_ACADEMIC_YEAR_START_MONTH', 9),

    'stages' => [
        'lead' => ['label_en' => 'Lead', 'label_ar' => 'عميل محتمل'],
        'inquiry' => ['label_en' => 'Inquiry', 'label_ar' => 'استفسار'],
        'campus_visit' => ['label_en' => 'Campus Visit', 'label_ar' => 'زيارة الحرم'],
        'application' => ['label_en' => 'Application', 'label_ar' => 'طلب التقديم'],
    ],

    'statuses' => [
        'open' => ['label_en' => 'Open', 'label_ar' => 'مفتوح'],
        'converted' => ['label_en' => 'Converted', 'label_ar' => 'تم التحويل'],
        'rejected' => ['label_en' => 'Rejected', 'label_ar' => 'مرفوض'],
        'withdrawn' => ['label_en' => 'Withdrawn', 'label_ar' => 'منسحب'],
        'waitlisted' => ['label_en' => 'Waitlisted', 'label_ar' => 'قائمة الانتظار'],
    ],

    'decisions' => [
        'accepted' => ['label_en' => 'Accepted', 'label_ar' => 'مقبول'],
        'rejected' => ['label_en' => 'Rejected', 'label_ar' => 'مرفوض'],
        'waitlisted' => ['label_en' => 'Waitlisted', 'label_ar' => 'قائمة الانتظار'],
        'withdrawn' => ['label_en' => 'Withdrawn', 'label_ar' => 'منسحب'],
        'converted' => ['label_en' => 'Converted', 'label_ar' => 'تم التحويل'],
    ],

    'visit_statuses' => [
        'requested' => ['label_en' => 'Requested', 'label_ar' => 'مطلوبة'],
        'confirmed' => ['label_en' => 'Confirmed', 'label_ar' => 'مؤكدة'],
        'completed' => ['label_en' => 'Completed', 'label_ar' => 'مكتملة'],
        'no_show' => ['label_en' => 'No Show', 'label_ar' => 'لم يحضر'],
        'cancelled' => ['label_en' => 'Cancelled', 'label_ar' => 'ملغاة'],
    ],

    'source_channels' => [
        'website_visit' => ['label_en' => 'Website Visit Form', 'label_ar' => 'نموذج زيارة الموقع'],
        'form_builder' => ['label_en' => 'Form Builder', 'label_ar' => 'منشئ النماذج'],
        'walk_in' => ['label_en' => 'Walk-in', 'label_ar' => 'زيارة مباشرة'],
        'referral' => ['label_en' => 'Referral', 'label_ar' => 'إحالة'],
        'phone' => ['label_en' => 'Phone', 'label_ar' => 'هاتف'],
    ],

    'priorities' => [
        'normal' => ['label_en' => 'Normal', 'label_ar' => 'عادي'],
        'high' => ['label_en' => 'High', 'label_ar' => 'عالي'],
    ],

    'document_definition_sources' => [
        'settings' => 'إعدادات القبول',
        'form_builder' => 'منشئ النماذج',
    ],

    'document_statuses' => [
        'needs_upload' => ['label_en' => 'Needs Upload', 'label_ar' => 'يحتاج رفع'],
        'review_pending' => ['label_en' => 'Under Review', 'label_ar' => 'قيد المراجعة'],
        'approved' => ['label_en' => 'Approved', 'label_ar' => 'معتمد'],
        'reupload_required' => ['label_en' => 'Re-upload Required', 'label_ar' => 'يحتاج إعادة رفع'],
        'rejected' => ['label_en' => 'Rejected', 'label_ar' => 'مرفوض'],
    ],

    'note_visibilities' => [
        'internal' => ['label_en' => 'Internal', 'label_ar' => 'داخلي'],
        'team' => ['label_en' => 'Team', 'label_ar' => 'الفريق'],
    ],

    'visit_outcomes' => [
        'interested' => ['label_en' => 'Interested', 'label_ar' => 'مهتم'],
        'highly_interested' => ['label_en' => 'Highly Interested', 'label_ar' => 'مهتم جداً'],
        'requested_application' => ['label_en' => 'Requested Application', 'label_ar' => 'طلب التقديم'],
        'waitlist_candidate' => ['label_en' => 'Waitlist Candidate', 'label_ar' => 'مرشح قائمة انتظار'],
        'not_interested' => ['label_en' => 'Not Interested', 'label_ar' => 'غير مهتم'],
        'positive' => ['label_en' => 'Positive', 'label_ar' => 'إيجابية'],
        'neutral' => ['label_en' => 'Neutral', 'label_ar' => 'محايدة'],
        'negative' => ['label_en' => 'Negative', 'label_ar' => 'سلبية'],
        'rescheduled' => ['label_en' => 'Rescheduled', 'label_ar' => 'أُعيدت جدولتها'],
    ],

    'visit_attendance_statuses' => [
        'attended' => ['label_en' => 'Attended', 'label_ar' => 'حضر'],
        'no_show' => ['label_en' => 'No Show', 'label_ar' => 'لم يحضر'],
        'cancelled' => ['label_en' => 'Cancelled', 'label_ar' => 'ملغاة'],
        'pending' => ['label_en' => 'Pending', 'label_ar' => 'قيد الانتظار'],
    ],

    'readiness' => [
        'documents_required_for_conversion' => (bool) env('ADMISSIONS_DOCUMENTS_REQUIRED_FOR_CONVERSION', false),
    ],

    'visits_calendar_limit' => (int) env('ADMISSIONS_VISITS_CALENDAR_LIMIT', 2000),
];
