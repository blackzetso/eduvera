<?php

namespace Tests\Unit\Dova;

use App\Services\Dova\DovaFaqService;
use App\Services\Dova\DovaKnowledgeQueryLogger;
use App\Services\Dova\DovaLLMService;
use App\Services\Website\WebsiteContentService;
use App\Support\Dova\DovaContextResolver;
use App\Support\Dova\DovaConversationIntentService;
use App\Support\Dova\DovaCopilotService;
use App\Support\Dova\DovaKnowledgeRetrievalNormalizer;
use App\Support\Dova\DovaKnowledgeService;
use App\Support\Dova\DovaPersonalityService;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class DovaCopilotServiceTest extends TestCase
{
    protected DovaCopilotService $service;

    protected DovaContextResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $mock = Mockery::mock(WebsiteContentService::class);
        $mock->shouldReceive('isCmsActive')->andReturn(false);
        $this->app->instance(WebsiteContentService::class, $mock);

        $faqMock = Mockery::mock(DovaFaqService::class);
        $faqMock->shouldReceive('publishedForKnowledge')->andReturn([]);
        $this->app->instance(DovaFaqService::class, $faqMock);

        config(['dova-ai.enabled' => false]);

        $this->resolver = new DovaContextResolver;
        $this->service = new DovaCopilotService(
            $this->resolver,
            new DovaPersonalityService,
            new DovaConversationIntentService,
            new DovaKnowledgeService(app(WebsiteContentService::class), app(DovaFaqService::class), new DovaKnowledgeRetrievalNormalizer),
            new DovaKnowledgeQueryLogger,
            new DovaLLMService(new DovaPersonalityService),
        );
    }

    public function test_suggests_create_student_for_admin_students_context(): void
    {
        config(['dova.enabled' => true]);

        $request = Request::create('/admin/students', 'GET');

        $response = $this->service->respondToMessage('how do I add a new student?', $request);

        $this->assertNotEmpty($response['actions']);
        $this->assertSame('create_student', $response['actions'][0]['id']);
        $this->assertStringContainsString('/admin/students/create', $response['actions'][0]['href']);
        $this->assertNotEmpty($response['introduction']);
        $this->assertNotEmpty($response['explanation']);
    }

    public function test_suggests_admissions_anchor_on_public_site(): void
    {
        config(['dova.enabled' => true]);

        $request = Request::create('/', 'GET');

        $response = $this->service->respondToMessage('how do I register my child online', $request);

        $ids = array_column($response['actions'], 'id');

        $this->assertContains('open_admissions', $ids);
        $this->assertNotEmpty($response['explanation']);
        $this->assertStringContainsString('admission', strtolower($response['introduction'].$response['explanation']));
    }

    public function test_quick_actions_are_portal_and_context_scoped(): void
    {
        $adminCtx = $this->resolver->resolve(Request::create('/admin/students', 'GET'));
        $publicCtx = $this->resolver->resolve(Request::create('/', 'GET'));

        $admin = $this->service->quickActions($adminCtx, 'en');
        $public = $this->service->quickActions($publicCtx, 'en');

        $adminIds = array_column($admin, 'id');
        $publicIds = array_column($public, 'id');

        $this->assertContains('create_student', $adminIds);
        $this->assertNotContains('create_student', $publicIds);
        $this->assertContains('open_admissions', $publicIds);
    }

    public function test_salaam_does_not_trigger_workflow_actions(): void
    {
        config(['dova.enabled' => true]);

        $response = $this->service->respondToMessage('السلام عليكم', Request::create('/', 'GET'));

        $this->assertSame('greeting', $response['intent']);
        $this->assertSame([], $response['actions']);
        $this->assertNull($response['workflow']);
        $this->assertStringContainsString('وعليكم السلام', $response['introduction']);
    }

    public function test_for_request_includes_premium_welcome_payload(): void
    {
        config(['dova.enabled' => true]);

        $payload = $this->service->forRequest(Request::create('/admin/attendances', 'GET'));

        $this->assertTrue($payload['enabled']);
        $this->assertArrayHasKey('welcome', $payload);
        $this->assertArrayHasKey('headline', $payload['welcome']);
        $this->assertArrayHasKey('body', $payload['welcome']);
        $this->assertArrayHasKey('statusLabel', $payload);
        $this->assertArrayNotHasKey('principles', $payload);
        $this->assertArrayNotHasKey('demoMode', $payload);
    }

    public function test_school_name_question_uses_knowledge_not_workflow_template(): void
    {
        config(['dova.enabled' => true, 'dova.knowledge_debug' => true]);

        $mock = Mockery::mock(WebsiteContentService::class);
        $mock->shouldReceive('isCmsActive')->andReturn(true);
        $mock->shouldReceive('forLanding')->with(false)->andReturn([
            'schoolInfo' => [
                'name' => 'Nile Private Schools',
                'tagline' => 'International School',
                'contact' => ['phone' => '+20 2 1234 5678', 'email' => 'admissions@nile.edu'],
            ],
        ]);
        $this->app->instance(WebsiteContentService::class, $mock);

        $faqMock = Mockery::mock(DovaFaqService::class);
        $faqMock->shouldReceive('publishedForKnowledge')->andReturn([]);
        $this->app->instance(DovaFaqService::class, $faqMock);

        $service = new DovaCopilotService(
            $this->resolver,
            new DovaPersonalityService,
            new DovaConversationIntentService,
            new DovaKnowledgeService(app(WebsiteContentService::class), app(DovaFaqService::class), new DovaKnowledgeRetrievalNormalizer),
            new DovaKnowledgeQueryLogger,
            new DovaLLMService(new DovaPersonalityService),
        );

        $response = $service->respondToMessage(
            'What is the name of this school?',
            Request::create('/', 'GET'),
        );

        $this->assertSame('knowledge', $response['intent']);
        $this->assertStringContainsString('Nile Private Schools', $response['introduction']);
        $this->assertArrayHasKey('knowledgeDebug', $response);
        $this->assertSame('school_info', $response['knowledgeDebug']['source']);
        $this->assertStringNotContainsString('admissions workflow', strtolower($response['explanation']));
    }
}
