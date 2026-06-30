<?php

namespace Tests\Unit\Translation;

use App\Services\Translation\BilingualAutoTranslationService;
use App\Services\Translation\BilingualFieldResolver;
use App\Services\Translation\Contracts\TranslationServiceInterface;
use App\Services\Translation\TranslationManager;
use Mockery;
use Tests\TestCase;

class BilingualAutoTranslationServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_fills_arabic_when_english_is_provided(): void
    {
        config(['translation.enabled' => true]);

        $translator = Mockery::mock(TranslationServiceInterface::class);
        $translator->shouldReceive('translateBatch')
            ->once()
            ->with(['title' => 'Science Exhibition'], 'en', 'ar')
            ->andReturn(['title' => 'معرض العلوم']);

        $manager = Mockery::mock(TranslationManager::class);
        $manager->shouldReceive('active')->andReturn($translator);

        $service = new BilingualAutoTranslationService($manager, new BilingualFieldResolver);

        $result = $service->translatePayload([
            'title' => 'Science Exhibition',
            'title_ar' => '',
        ]);

        $this->assertSame('Science Exhibition', $result['title']);
        $this->assertSame('معرض العلوم', $result['title_ar']);
    }

    public function test_fills_english_when_arabic_is_provided(): void
    {
        config(['translation.enabled' => true]);

        $translator = Mockery::mock(TranslationServiceInterface::class);
        $translator->shouldReceive('translateBatch')
            ->once()
            ->with(['label' => 'فتح باب التسجيل'], 'ar', 'en')
            ->andReturn(['label' => 'Registration Open']);

        $manager = Mockery::mock(TranslationManager::class);
        $manager->shouldReceive('active')->andReturn($translator);

        $service = new BilingualAutoTranslationService($manager, new BilingualFieldResolver);

        $result = $service->translatePayload([
            'label' => '',
            'label_ar' => 'فتح باب التسجيل',
        ]);

        $this->assertSame('Registration Open', $result['label']);
        $this->assertSame('فتح باب التسجيل', $result['label_ar']);
    }

    public function test_does_not_translate_when_both_languages_are_present(): void
    {
        config(['translation.enabled' => true]);

        $manager = Mockery::mock(TranslationManager::class);
        $manager->shouldNotReceive('active');

        $service = new BilingualAutoTranslationService($manager, new BilingualFieldResolver);

        $payload = [
            'title' => 'English title',
            'title_ar' => 'عنوان عربي',
        ];

        $this->assertSame($payload, $service->translatePayload($payload));
    }

    public function test_translates_nested_array_items(): void
    {
        config(['translation.enabled' => true]);

        $translator = Mockery::mock(TranslationServiceInterface::class);
        $translator->shouldReceive('translateBatch')
            ->once()
            ->with(['title' => 'Mission'], 'en', 'ar')
            ->andReturn(['title' => 'رسالتنا']);

        $manager = Mockery::mock(TranslationManager::class);
        $manager->shouldReceive('active')->andReturn($translator);

        $service = new BilingualAutoTranslationService($manager, new BilingualFieldResolver);

        $result = $service->translatePayload([
            'items' => [
                ['title' => 'Mission', 'title_ar' => ''],
            ],
        ]);

        $this->assertSame('رسالتنا', $result['items'][0]['title_ar']);
    }
}
