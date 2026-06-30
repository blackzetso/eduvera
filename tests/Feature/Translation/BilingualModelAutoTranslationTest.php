<?php

namespace Tests\Feature\Translation;

use App\Models\Website\WebsiteAnnouncement;
use App\Services\Translation\Contracts\TranslationServiceInterface;
use App\Services\Translation\TranslationManager;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Mockery;
use Tests\TestCase;

class BilingualModelAutoTranslationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('website_announcements');
        Schema::create('website_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable();
            $table->string('text');
            $table->string('text_ar')->nullable();
            $table->string('href')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('website_announcements');
        Mockery::close();
        parent::tearDown();
    }

    public function test_model_save_auto_fills_missing_arabic_text(): void
    {
        config(['translation.enabled' => true]);

        $translator = Mockery::mock(TranslationServiceInterface::class);
        $translator->shouldReceive('translateBatch')
            ->once()
            ->with(['text' => 'Open Day'], 'en', 'ar')
            ->andReturn(['text' => 'يوم مفتوح']);

        $manager = Mockery::mock(TranslationManager::class);
        $manager->shouldReceive('active')->andReturn($translator);
        $this->app->instance(TranslationManager::class, $manager);

        $announcement = WebsiteAnnouncement::query()->create([
            'text' => 'Open Day',
            'text_ar' => '',
            'href' => '#',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->assertSame('يوم مفتوح', $announcement->fresh()->text_ar);
    }
}
