<?php

namespace Tests\Unit\Dova;

use App\Support\Dova\DovaPersonalityService;
use Tests\TestCase;

class DovaPersonalityServiceTest extends TestCase
{
    protected DovaPersonalityService $personality;

    protected function setUp(): void
    {
        parent::setUp();
        $this->personality = new DovaPersonalityService;
    }

    public function test_admissions_response_is_natural_in_english(): void
    {
        $ctx = ['role' => 'guest', 'portal' => 'public', 'page_context' => 'home'];
        $actions = [['id' => 'open_admissions', 'label' => 'Open Admissions', 'expression' => 'helping']];

        $response = $this->personality->buildResponse('how do I apply?', $actions, $ctx, 'en');

        $this->assertStringContainsString('happy to help', strtolower($response['introduction']));
        $this->assertStringContainsString('application', strtolower($response['explanation']));
        $this->assertStringContainsString('buttons below', strtolower($response['footer']));
        $this->assertNotSame('Open Admissions', $response['text']);
    }

    public function test_admissions_response_is_natural_in_arabic(): void
    {
        $ctx = ['role' => 'guest', 'portal' => 'public', 'page_context' => 'home'];
        $actions = [['id' => 'open_admissions', 'label' => 'فتح القبول', 'expression' => 'helping']];

        $response = $this->personality->buildResponse('كيف أقدّم للقبول؟', $actions, $ctx, 'ar');

        $this->assertStringContainsString('يسعدني', $response['introduction']);
        $this->assertStringContainsString('التقديم', $response['explanation']);
        $this->assertStringContainsString('الأزرار', $response['footer']);
    }

    public function test_guardian_welcome_is_reassuring(): void
    {
        $ctx = ['role' => 'guardian', 'portal' => 'guardian', 'page_context' => 'dashboard'];

        $welcome = $this->personality->welcomeCard($ctx, 'en');

        $this->assertStringContainsString("child's progress", $welcome['body']);
        $this->assertSame('Online', $welcome['status']);
    }

    public function test_empty_actions_returns_recovery_expression(): void
    {
        $ctx = ['role' => 'guest', 'portal' => 'public', 'page_context' => 'home'];

        $response = $this->personality->buildResponse('xyz unknown', [], $ctx, 'en');

        $this->assertSame('help', $response['expression']);
        $this->assertStringContainsString('rephrasing', strtolower($response['explanation']));
    }
}
