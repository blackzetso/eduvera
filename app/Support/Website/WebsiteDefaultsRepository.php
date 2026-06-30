<?php

namespace App\Support\Website;

class WebsiteDefaultsRepository
{
    public static function path(): string
    {
        return database_path('data/school-talent-cms-defaults.json');
    }

    public static function load(): array
    {
        $path = static::path();
        if (! is_file($path)) {
            return [];
        }

        $data = json_decode(file_get_contents($path), true);

        return is_array($data) ? array_replace_recursive(static::builtinDefaults(), $data) : static::builtinDefaults();
    }

    public static function builtinDefaults(): array
    {
        return [
            'headerChrome' => [
                'announcement_badge' => 'New',
                'header_ctas' => [
                    ['id' => 'visit', 'label' => 'Visit', 'href' => '#visit', 'variant' => 'outline'],
                    ['id' => 'apply', 'label' => 'Apply', 'href' => '#admissions', 'variant' => 'outline'],
                ],
                'login' => ['label' => 'Login', 'href' => '/login', 'visible' => true],
                'use_logo_image' => true,
                'logo_mark_fallback' => 'NPS',
            ],
            'footerChrome' => [
                'tagline' => 'Premium international education — nurturing future leaders since 1998.',
                'copyright' => '© {year} {school_name}. All rights reserved.',
                'columns' => [
                    ['title' => 'About', 'links' => [
                        ['label' => 'Our Story', 'href' => '#about'],
                        ['label' => 'Leadership', 'href' => '#about'],
                        ['label' => 'Accreditations', 'href' => '#partners'],
                    ]],
                    ['title' => 'Admissions', 'links' => [
                        ['label' => 'Apply Now', 'href' => '#visit'],
                        ['label' => 'Book a Visit', 'href' => '#visit'],
                    ]],
                    ['title' => 'Contact', 'links' => [
                        ['label' => 'Visit Us', 'href' => '#contact'],
                        ['label' => 'Email', 'href' => '#contact'],
                    ]],
                ],
                'newsletter' => [
                    'enabled' => true,
                    'title' => 'Newsletter',
                    'description' => 'School updates, events, and parent guides.',
                    'placeholder' => 'Your email',
                    'button_label' => 'Subscribe',
                    'submit_url' => null,
                ],
                'legal_links' => [
                    ['label' => 'Privacy', 'href' => '/privacy'],
                    ['label' => 'Terms', 'href' => '/terms'],
                    ['label' => 'Cookies', 'href' => '/cookies'],
                ],
            ],
            'ctaLibrary' => [
                ['id' => 'apply', 'label' => 'Explore Admissions', 'href' => '#admissions', 'variant' => 'gold'],
                ['id' => 'visit', 'label' => 'Visit', 'href' => '#visit', 'variant' => 'outline'],
                ['id' => 'info', 'label' => 'Request Information', 'href' => '#visit', 'variant' => 'outline'],
                ['id' => 'teacher', 'label' => 'Become a Teacher', 'href' => '#careers', 'variant' => 'gold'],
                ['id' => 'contact', 'label' => 'Contact Admissions', 'href' => '#contact', 'variant' => 'outline'],
                ['id' => 'learnMore', 'label' => 'View Our Programs', 'href' => '#stages', 'variant' => 'outline'],
                ['id' => 'viewAllEvents', 'label' => 'View All Events', 'href' => '#news', 'variant' => 'outline'],
                ['id' => 'readMore', 'label' => 'Read More', 'href' => '#about', 'variant' => 'outline'],
                ['id' => 'viewNewsBlog', 'label' => 'View News & Blog', 'href' => '#news', 'variant' => 'outline'],
            ],
            'stageModalUi' => [
                'tabs' => [
                    ['id' => 'overview', 'label' => 'Overview'],
                    ['id' => 'curriculum', 'label' => 'Curriculum'],
                    ['id' => 'activities', 'label' => 'Activities'],
                    ['id' => 'schedule', 'label' => 'Daily Schedule'],
                    ['id' => 'outcomes', 'label' => 'Learning Outcomes'],
                    ['id' => 'gallery', 'label' => 'Gallery'],
                    ['id' => 'teachers', 'label' => 'Teachers'],
                    ['id' => 'faq', 'label' => 'Parent FAQ'],
                    ['id' => 'admission', 'label' => 'Admission'],
                ],
                'paneTitles' => [
                    'curriculum' => 'Subjects & Program',
                    'activities' => 'Activities & Student Life',
                    'schedule' => 'Daily Schedule',
                    'outcomes' => 'Learning Outcomes',
                    'teachers' => 'Our Educators',
                    'faq' => 'Parent FAQ',
                    'admission' => 'Admission Requirements',
                ],
                'footer' => [
                    'applyCtaId' => 'apply',
                    'visitCtaId' => 'visit',
                    'closeLabel' => 'Close',
                    'applyLabel' => 'Apply For This Stage',
                ],
            ],
            'campusVisit' => [
                'title' => 'Why Visit Our Campus?',
                'lead' => 'See School Talent in person and get answers from our admissions team in one visit.',
                'hero_badge' => 'Campus Tours',
                'book_button_label' => 'Book a Campus Tour',
                'form_title' => 'Book Your Campus Visit',
                'form_subtitle' => 'Complete the form below — we will email you a confirmation with your visit reference number.',
                'back_label' => 'Back',
            ],
            'admissionDocuments' => [
                ['label' => 'Birth certificate (copy)', 'required' => true],
                ['label' => 'Previous school reports (2 years)', 'required' => true],
                ['label' => 'Passport-size photographs', 'required' => true],
                ['label' => 'Immunization record', 'required' => false],
            ],
            'uiLabels' => \App\Support\Website\WebsiteUiLabelDefaults::all(),
            'visitFormConfig' => [
                'formId' => 'school-talent-visit',
                'fields' => \App\Support\Website\WebsiteUiLabelDefaults::defaultVisitFormFields(),
                'gradeOptions' => [
                    'Nursery / Early Years',
                    'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6',
                    'Grade 7', 'Grade 8', 'Grade 9',
                    'Grade 10', 'Grade 11', 'Grade 12',
                    'Not yet in school',
                ],
                'timeSlots' => ['9:00 AM', '10:00 AM', '11:00 AM', '12:00 PM', '1:00 PM', '2:00 PM'],
                'labels' => ['submit' => 'Submit Visit Request'],
                'hint' => null,
            ],
            'floatingChrome' => [
                'admissions_panel_enabled' => true,
                'whatsapp_enabled' => true,
                'back_to_top_enabled' => true,
                'whatsapp_label' => 'WhatsApp Admissions',
                'panel_actions' => [
                    ['cta_id' => 'apply', 'icon' => 'bi-pencil-square', 'variant' => 'primary'],
                    ['cta_id' => 'visit', 'icon' => 'bi-calendar-check', 'variant' => 'outline'],
                    ['cta_id' => 'info', 'icon' => 'bi-envelope', 'variant' => 'outline'],
                ],
            ],
        ];
    }
}
