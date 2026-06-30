<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Multilingual concept intents (language-independent retrieval)
    |--------------------------------------------------------------------------
    |
    | Each concept may match via:
    | - phrases: substring match on normalized query
    | - token_groups: at least one token from EACH group must appear in query
    |
    */
    'concepts' => [
        'school_name' => [
            'confidence' => 0.97,
            'phrases' => [
                'name of the school', 'name of this school', 'school name', 'what is the name',
                'اسم المدرسة', 'ما اسم المدرسة', 'ما اسم هذه المدرسة',
            ],
            'token_groups' => [
                ['school', 'مدرسة', 'اسم', 'name'],
                ['name', 'اسم', 'ما', 'what'],
            ],
        ],

        'school_phone' => [
            'confidence' => 0.96,
            'phrases' => [
                'school phone', 'school phone number', 'contact number', 'call the school', 'telephone',
                'رقم الهاتف', 'رقم التليفون', 'هاتف المدرسة', 'تليفون المدرسة',
            ],
            'token_groups' => [
                ['phone', 'telephone', 'tel', 'call', 'هاتف', 'تليفون', 'رقم'],
                ['school', 'مدرسة'],
            ],
        ],

        'school_email' => [
            'confidence' => 0.94,
            'phrases' => [
                'school email', 'email address', 'contact email',
                'البريد الإلكتروني', 'البريد الالكتروني', 'ايميل المدرسة', 'الايميل', 'بريد المدرسة',
            ],
            'token_groups' => [
                ['email', 'mail', 'بريد', 'ايميل', 'الكتروني'],
                ['school', 'مدرسة'],
            ],
        ],

        'school_address' => [
            'confidence' => 0.94,
            'phrases' => [
                'school address', 'where is the school', 'school location',
                'عنوان المدرسة', 'اين تقع المدرسة', 'أين تقع المدرسة', 'موقع المدرسة',
            ],
            'token_groups' => [
                ['address', 'location', 'where', 'عنوان', 'موقع', 'اين', 'أين'],
                ['school', 'مدرسة'],
            ],
        ],

        'school_hours' => [
            'confidence' => 0.9,
            'phrases' => [
                'school hours', 'opening hours', 'office hours',
                'ساعات العمل', 'مواعيد المدرسة', 'مواعيد العمل',
            ],
            'token_groups' => [
                ['hours', 'time', 'open', 'ساعات', 'مواعيد', 'وقت'],
                ['school', 'مدرسة', 'عمل'],
            ],
        ],

        'admission_requirements' => [
            'confidence' => 0.95,
            'phrases' => [
                'admission requirement', 'requirements for admission', 'what do i need to apply',
                'documents needed', 'documents required',
                'متطلبات القبول', 'متطلبات التقديم', 'ماذا احتاج للقبول', 'ماذا أحتاج للقبول',
                'اوراق القبول', 'أوراق القبول', 'شروط القبول',
            ],
            'token_groups' => [
                ['admission', 'admissions', 'apply', 'application', 'قبول', 'التقديم', 'تقديم'],
                ['requirement', 'requirements', 'document', 'documents', 'need', 'متطلبات', 'اوراق', 'أوراق', 'شروط', 'ماذا'],
            ],
        ],

        'programs' => [
            'confidence' => 0.94,
            'phrases' => [
                'what programs', 'programs do you offer', 'academic programs', 'what stages', 'curriculum',
                'البرامج الدراسية', 'ما البرامج', 'المراحل الدراسية', 'المنهج',
            ],
            'token_groups' => [
                ['program', 'programs', 'curriculum', 'stage', 'stages', 'برنامج', 'برامج', 'مراحل', 'منهج'],
            ],
        ],

        'admissions_contact' => [
            'confidence' => 0.95,
            'phrases' => [
                'contact admissions', 'admissions contact', 'admission contact', 'contact for admission',
                'admissions phone', 'admissions phone number', 'admissions email', 'admissions whatsapp', 'admission phone',
                'admission email', 'admission office', 'admission department', 'call admissions',
                'email admissions', 'how can i contact admissions', 'who can i contact for admission',
                'who should i contact for admission',
                'التواصل مع القبول', 'أتواصل مع القبول', 'اتواصل مع القبول', 'تواصل مع القبول',
                'أريد التواصل مع القبول', 'اريد التواصل مع القبول', 'كيف أتواصل مع القبول',
                'كيف اتواصل مع القبول', 'من أتواصل معه للقبول', 'من اتواصل معه للقبول',
                'رقم القبول', 'بريد القبول', 'واتساب القبول', 'هاتف القبول', 'ايميل القبول',
                'إدارة القبول', 'ادارة القبول', 'كلم القبول', 'أكلم القبول', 'اكلم القبول',
                'كيف أكلم إدارة القبول', 'كيف اكلم إدارة القبول', 'مسؤول القبول', 'فريق القبول',
                'للتواصل مع القبول', 'تواصل القبول',
            ],
            'token_groups' => [
                ['admission', 'admissions', 'admission office', 'admission department', 'قبول', 'التقديم', 'تقديم', 'إدارة القبول', 'ادارة القبول'],
                ['contact', 'phone', 'email', 'whatsapp', 'call', 'number', 'reach', 'تواصل', 'اتواصل', 'أتواصل', 'رقم', 'هاتف', 'بريد', 'واتساب', 'اكلم', 'أكلم', 'كلم', 'كيف', 'how', 'ايميل', 'أريد', 'اريد', 'من'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cross-language synonyms (expand query for corpus / FAQ matching)
    |--------------------------------------------------------------------------
    */
    'synonyms' => [
        'admissions' => ['admission', 'admissions', 'apply', 'application', 'enrolment', 'enrollment', 'القبول', 'قبول', 'التقديم', 'تقديم', 'إدارة القبول', 'ادارة القبول'],
        'contact' => ['contact', 'phone', 'email', 'whatsapp', 'call', 'reach', 'number', 'تواصل', 'اتواصل', 'أتواصل', 'رقم', 'هاتف', 'بريد', 'واتساب', 'اكلم', 'أكلم', 'كلم', 'ايميل'],
        'school' => ['school', 'academy', 'institute', 'مدرسة', 'مدرسه', 'المدرسة'],
        'fees' => ['fee', 'fees', 'tuition', 'cost', 'price', 'رسوم', 'مصاريف', 'تكلفة'],
        'programs' => ['program', 'programs', 'curriculum', 'stage', 'برنامج', 'برامج', 'مرحلة', 'مراحل'],
    ],

];
