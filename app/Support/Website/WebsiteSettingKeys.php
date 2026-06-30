<?php

namespace App\Support\Website;

final class WebsiteSettingKeys
{
    public const HEADER_CHROME = 'header_chrome';

    public const FOOTER_CHROME = 'footer_chrome';

    public const CTA_LIBRARY = 'cta_library';

    public const CAMPUS_VISIT = 'campus_visit';

    public const ADMISSION_DOCUMENTS = 'admission_documents';

    public const FLOATING_CHROME = 'floating_chrome';

    public const UI_LABELS = 'ui_labels';

    /** @return array<string, string> slug => setting key */
    public static function contentBlockMap(): array
    {
        return [
            'trust-strip' => 'trust_items',
            'core-values' => 'core_values',
            'why-choose' => 'why_items',
            'parent-trust' => 'parent_trust_strip',
            'academic-programs' => 'academic_programs',
            'achievements' => 'achievements',
            'accreditations' => 'accreditations',
            'faqs' => 'faqs',
            'gallery-categories' => 'gallery_categories',
            'hero-badges' => 'hero_badges',
            'hero-highlights' => 'hero_highlights',
            'stage-showcase-labels' => 'stage_showcase_labels',
            'stage-modal-ui' => 'stage_modal_ui',
            'student-life' => 'student_life',
        ];
    }

    public static function contentBlockTitle(string $slug): string
    {
        return match ($slug) {
            'trust-strip' => 'Trust Strip',
            'core-values' => 'Core Values',
            'why-choose' => 'Why Choose Us',
            'parent-trust' => 'Parent Trust Band',
            'academic-programs' => 'Academic Programs',
            'achievements' => 'Achievements',
            'accreditations' => 'Accreditations',
            'faqs' => 'FAQs',
            'gallery-categories' => 'Gallery Categories',
            'hero-badges' => 'Hero Badges',
            'hero-highlights' => 'Hero Highlights',
            'stage-showcase-labels' => 'Stage Showcase Labels',
            'stage-modal-ui' => 'Stage Detail Modal',
            'student-life' => 'Student Life Tiles',
            default => $slug,
        };
    }
}
