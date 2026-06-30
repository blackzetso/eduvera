<?php

namespace Tests\Feature\Translation;

use App\Models\Website\WebsiteSetting;
use App\Services\Translation\Contracts\TranslationServiceInterface;
use App\Services\Translation\TranslationManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class BilingualSettingAutoTranslationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('website_settings');
        Schema::create('website_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('website_settings');
        Mockery::close();
        parent::tearDown();
    }

    public function test_setting_put_value_auto_fills_missing_arabic_fields(): void
    {
        config(['translation.enabled' => true]);

        $translator = Mockery::mock(TranslationServiceInterface::class);
        $translator->shouldReceive('translateBatch')
            ->once()
            ->with(['announcement_badge' => 'New'], 'en', 'ar')
            ->andReturn(['announcement_badge' => 'جديد']);

        $manager = Mockery::mock(TranslationManager::class);
        $manager->shouldReceive('active')->andReturn($translator);
        $this->app->instance(TranslationManager::class, $manager);

        WebsiteSetting::putValue('header_chrome', [
            'announcement_badge' => 'New',
            'announcement_badge_ar' => '',
        ]);

        $stored = WebsiteSetting::getValue('header_chrome');

        $this->assertSame('New', $stored['announcement_badge']);
        $this->assertSame('جديد', $stored['announcement_badge_ar']);
    }
}
