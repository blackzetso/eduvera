<?php

return [
    'labels' => [
        'admissions' => [
            'decision_accept' => 'قبول الطلب',
            'decision_reject' => 'رفض الطلب',
            'decision_waitlist' => 'قائمة الانتظار',
            'decision_withdraw' => 'انسحاب الطلب',
            'convert' => 'تحويل إلى طالب',
            'document_approved' => 'اعتماد مستند',
            'document_reupload_required' => 'طلب إعادة رفع مستند',
            'document_rejected' => 'رفض مستند',
        ],
        'lifecycle' => [
            'promote' => 'ترقية الطالب',
            'transfer' => 'نقل الطالب',
            'withdraw' => 'انسحاب الطالب',
            're_enroll' => 'إعادة قيد',
            'graduate' => 'تخريج الطالب',
            'change_status' => 'تغيير حالة الطالب',
            'link_guardian' => 'ربط ولي أمر',
        ],
        'family' => [
            'link_student' => 'ربط ابن بالعائلة',
            'remove_student_link' => 'إزالة ربط ابن',
            'edit_profile' => 'تعديل ملف العائلة',
        ],
    ],

    'icons' => [
        'admissions' => [
            'decision_accept' => 'bi-check-circle',
            'decision_reject' => 'bi-x-circle',
            'decision_waitlist' => 'bi-hourglass',
            'decision_withdraw' => 'bi-box-arrow-left',
            'convert' => 'bi-person-plus',
            'document_approved' => 'bi-file-earmark-check',
            'document_reupload_required' => 'bi-arrow-repeat',
            'document_rejected' => 'bi-file-earmark-x',
        ],
        'lifecycle' => [
            'promote' => 'bi-arrow-up-circle',
            'transfer' => 'bi-arrow-left-right',
            'withdraw' => 'bi-box-arrow-right',
            're_enroll' => 'bi-arrow-repeat',
            'graduate' => 'bi-mortarboard',
            'change_status' => 'bi-toggle-on',
            'link_guardian' => 'bi-people',
        ],
        'family' => [
            'link_student' => 'bi-link-45deg',
            'remove_student_link' => 'bi-link-45deg',
            'edit_profile' => 'bi-pencil-square',
        ],
    ],
];
