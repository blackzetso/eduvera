<?php

namespace App\Support\Website;

final class WebsiteUiLabelDefaults
{
    public static function all(): array
    {
        return [
            'global' => [
                'mission_label' => 'Mission:',
                'vision_label' => 'Vision:',
                'read_more' => 'Read more',
                'verify_accreditation' => 'Verify accreditation',
                'open_positions' => 'Open positions:',
                'gallery_all' => 'All',
                'submit_visit_request' => 'Submit Visit Request',
            ],
            'cta' => [
                'apply' => 'Apply Now',
                'visit' => 'Book a Visit',
                'info' => 'Request Information',
                'contact' => 'Contact Admissions',
                'teacher' => 'Become a Teacher',
                'learnMore' => 'Learn More',
                'viewAllEvents' => 'View All Events',
                'readMore' => 'Read More',
                'viewNewsBlog' => 'View News & Blog',
            ],
            'hero' => [
                'trust_avatars' => [
                    ['mode' => 'initial', 'value' => 'A'],
                    ['mode' => 'initial', 'value' => 'B'],
                    ['mode' => 'initial', 'value' => 'C'],
                    ['mode' => 'initial', 'value' => 'D'],
                ],
            ],
        ];
    }

    /** @return array<int, array{key: string, label: string, group: string}> */
    public static function adminFields(): array
    {
        return [
            ['group' => 'global', 'key' => 'mission_label', 'label' => 'Mission label'],
            ['group' => 'global', 'key' => 'vision_label', 'label' => 'Vision label'],
            ['group' => 'global', 'key' => 'read_more', 'label' => 'Read more'],
            ['group' => 'global', 'key' => 'verify_accreditation', 'label' => 'Verify accreditation'],
            ['group' => 'global', 'key' => 'open_positions', 'label' => 'Open positions'],
            ['group' => 'global', 'key' => 'gallery_all', 'label' => 'Gallery filter: All'],
            ['group' => 'global', 'key' => 'submit_visit_request', 'label' => 'Visit form submit button'],
            ['group' => 'cta', 'key' => 'apply', 'label' => 'Apply Now'],
            ['group' => 'cta', 'key' => 'visit', 'label' => 'Book a Visit'],
            ['group' => 'cta', 'key' => 'info', 'label' => 'Request Information'],
            ['group' => 'cta', 'key' => 'contact', 'label' => 'Contact Admissions'],
            ['group' => 'cta', 'key' => 'teacher', 'label' => 'Become a Teacher'],
            ['group' => 'cta', 'key' => 'learnMore', 'label' => 'Learn More'],
            ['group' => 'cta', 'key' => 'viewAllEvents', 'label' => 'View All Events'],
            ['group' => 'cta', 'key' => 'readMore', 'label' => 'Read More (about)'],
            ['group' => 'cta', 'key' => 'viewNewsBlog', 'label' => 'View News & Blog'],
        ];
    }

    public static function defaultVisitFormFields(): array
    {
        return [
            ['key' => 'parentName', 'name' => 'parent_name', 'type' => 'text', 'enabled' => true, 'required' => true, 'sort' => 10, 'rowPair' => 'names', 'label' => 'Parent Name', 'placeholder' => ''],
            ['key' => 'studentName', 'name' => 'student_name', 'type' => 'text', 'enabled' => true, 'required' => true, 'sort' => 20, 'rowPair' => 'names', 'label' => 'Student Name', 'placeholder' => ''],
            ['key' => 'currentGrade', 'name' => 'current_grade', 'type' => 'select', 'optionsSource' => 'gradeOptions', 'enabled' => true, 'required' => true, 'sort' => 30, 'label' => 'Current Grade', 'placeholder' => 'Select grade'],
            ['key' => 'phone', 'name' => 'phone', 'type' => 'tel', 'enabled' => true, 'required' => true, 'sort' => 40, 'rowPair' => 'contact', 'label' => 'Phone Number', 'placeholder' => ''],
            ['key' => 'email', 'name' => 'email', 'type' => 'email', 'enabled' => true, 'required' => true, 'sort' => 50, 'rowPair' => 'contact', 'label' => 'Email', 'placeholder' => ''],
            ['key' => 'visitDate', 'name' => 'visit_date', 'type' => 'date', 'enabled' => true, 'required' => true, 'sort' => 60, 'rowPair' => 'schedule', 'label' => 'Preferred Visit Date', 'placeholder' => ''],
            ['key' => 'visitTime', 'name' => 'visit_time', 'type' => 'select', 'optionsSource' => 'timeSlots', 'enabled' => true, 'required' => true, 'sort' => 70, 'rowPair' => 'schedule', 'label' => 'Preferred Visit Time', 'placeholder' => 'Select time'],
            ['key' => 'notes', 'name' => 'notes', 'type' => 'textarea', 'enabled' => true, 'required' => false, 'sort' => 80, 'label' => 'Additional Notes', 'placeholder' => ''],
        ];
    }
}
