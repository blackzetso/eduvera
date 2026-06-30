<?php

namespace Tests\Feature\FormBuilder;

use App\Http\Controllers\admin\FormController;
use App\Services\FormBuilder\FormBuilderPersistenceService;
use App\Services\Translation\BilingualAutoTranslationService;
use App\Services\Translation\Contracts\TranslationServiceInterface;
use App\Services\Translation\TranslationManager;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class FormBilingualTranslateEndpointTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_translate_bilingual_action_fills_missing_arabic_field_name(): void
    {
        config(['translation.enabled' => true]);

        $translator = Mockery::mock(TranslationServiceInterface::class);
        $translator->shouldReceive('translateBatch')
            ->once()
            ->with(['name_en' => 'Full Name'], 'en', 'ar')
            ->andReturn(['name_en' => 'الاسم الكامل']);

        $manager = Mockery::mock(TranslationManager::class);
        $manager->shouldReceive('active')->andReturn($translator);
        $this->app->instance(TranslationManager::class, $manager);

        $builder = Mockery::mock(FormBuilderPersistenceService::class);
        $controller = new FormController($builder, app(BilingualAutoTranslationService::class));

        $response = $controller->translateBilingual(Request::create('/admin/forms/translate-bilingual', 'POST', [
            'payload' => [
                'name_en' => 'Full Name',
                'name_ar' => '',
            ],
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Full Name', $response->getData(true)['payload']['name_en']);
        $this->assertSame('الاسم الكامل', $response->getData(true)['payload']['name_ar']);
    }
}
