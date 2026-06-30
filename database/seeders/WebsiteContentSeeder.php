<?php

namespace Database\Seeders;

use App\Models\Website\WebsiteLandingPage;
use App\Services\Website\WebsiteContentService;
use App\Services\Website\WebsiteLandingBuilderService;
use Illuminate\Database\Seeder;

class WebsiteContentSeeder extends Seeder
{
    public function run(): void
    {
        app(WebsiteContentService::class)->importDefaults();

        $slug = config('website-landing-blocks.page_slug', 'school-talent');
        if (! WebsiteLandingPage::query()->where('slug', $slug)->exists()) {
            app(WebsiteLandingBuilderService::class)->seedPageFromLegacySettings();
        }
    }
}
