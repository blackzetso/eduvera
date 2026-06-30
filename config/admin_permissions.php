<?php

/**
 * EDUVERA admin RBAC — role → permission matrix.
 * Admins with null role default to super_admin (backward compatible).
 */
return [
    'default_role' => 'super_admin',

    'roles' => [
        'super_admin' => 'Super Admin',
        'principal' => 'Principal',
        'admissions_officer' => 'Admissions Officer',
        'registrar' => 'Registrar',
        'finance_officer' => 'Finance Officer',
        'hr_officer' => 'HR Officer',
    ],

    'permissions' => [
        'forms.manage',
        'forms.submissions.view',
        'forms.submissions.review',
        'admissions.view',
        'admissions.manage',
        'admissions.accept',
        'admissions.reject',
        'admissions.waitlist',
        'admissions.withdraw',
        'admissions.convert',
        'lifecycle.promote',
        'lifecycle.transfer',
        'lifecycle.withdraw',
        'lifecycle.re_enroll',
        'lifecycle.graduate',
        'lifecycle.change_status',
        'lifecycle.link_guardian',
        'family.edit_profile',
        'family.link_student',
        'family.remove_link',
        'finance.wallet_adjust',
        'finance.installment_override',
        'finance.financial_correction',
    ],

    'matrix' => [
        'super_admin' => '*',

        'principal' => [
            'admissions.view',
            'admissions.manage',
            'admissions.accept',
            'admissions.reject',
            'admissions.waitlist',
            'admissions.withdraw',
            'admissions.convert',
            'lifecycle.promote',
            'lifecycle.transfer',
            'lifecycle.withdraw',
            'lifecycle.re_enroll',
            'lifecycle.graduate',
            'lifecycle.change_status',
            'lifecycle.link_guardian',
            'family.edit_profile',
            'family.link_student',
            'family.remove_link',
            'finance.wallet_adjust',
            'finance.installment_override',
            'finance.financial_correction',
        ],

        'admissions_officer' => [
            'admissions.view',
            'admissions.manage',
            'admissions.accept',
            'admissions.reject',
            'admissions.waitlist',
            'admissions.withdraw',
        ],

        'registrar' => [
            'admissions.view',
            'admissions.manage',
            'admissions.withdraw',
            'admissions.convert',
            'lifecycle.promote',
            'lifecycle.transfer',
            'lifecycle.withdraw',
            'lifecycle.re_enroll',
            'lifecycle.graduate',
            'lifecycle.change_status',
            'lifecycle.link_guardian',
            'family.edit_profile',
            'family.link_student',
            'family.remove_link',
        ],

        'finance_officer' => [
            'admissions.view',
            'finance.wallet_adjust',
            'finance.installment_override',
            'finance.financial_correction',
        ],

        'hr_officer' => [
            'admissions.view',
            'forms.submissions.view',
            'forms.submissions.review',
            'family.edit_profile',
            'family.link_student',
        ],
    ],
];
