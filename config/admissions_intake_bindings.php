<?php

return [
    'campus_visit_primary' => [
        'binding_key' => 'campus_visit_primary',
        'enabled' => (bool) env('ADMISSIONS_BRIDGE_CAMPUS_VISIT_ENABLED', false),
        'form_id' => env('ADMISSIONS_BRIDGE_CAMPUS_VISIT_FORM_ID') !== null
            ? (int) env('ADMISSIONS_BRIDGE_CAMPUS_VISIT_FORM_ID')
            : null,
        'mapped_form_version' => env('ADMISSIONS_BRIDGE_CAMPUS_VISIT_FORM_VERSION') !== null
            ? (int) env('ADMISSIONS_BRIDGE_CAMPUS_VISIT_FORM_VERSION')
            : null,
        'mapping_profile' => 'admissions_visit_v1',
        'intake_role' => 'campus_visit',
        'target_phase' => 'evaluate_activity',
        'evaluate_activity_type' => 'campus_visit',
        'v1_pipeline_stage' => 'campus_visit',
        'creates_case' => true,
        'creates_household' => false,
        'duplicate_policy' => 'same_cycle_link',
        'cycle_scope' => 'academic_year',
        'source_channel' => 'form_builder',
        'field_map' => [
            'applicant.first_name' => 'fld_4',
            'contact.name' => 'fld_5',
            'contact.phone' => 'fld_6',
            'contact.email' => 'fld_7',
            'applicant.current_grade_label' => 'fld_8',
            'visit.scheduled_date' => 'fld_9',
            'visit.scheduled_time' => 'fld_10',
            'visit.notes' => 'fld_11',
        ],
    ],
];
