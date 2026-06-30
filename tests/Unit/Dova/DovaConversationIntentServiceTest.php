<?php

namespace Tests\Unit\Dova;

use App\Support\Dova\DovaConversationIntentService;
use Tests\TestCase;

class DovaConversationIntentServiceTest extends TestCase
{
    protected DovaConversationIntentService $intents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->intents = new DovaConversationIntentService;
    }

    public function test_salaam_greeting_returns_conversational_response_without_actions(): void
    {
        $ctx = ['role' => 'guest', 'portal' => 'public', 'page_context' => 'home'];

        $this->assertSame('greeting', $this->intents->detect('السلام عليكم'));

        $response = $this->intents->respond('greeting', 'السلام عليكم', $ctx, 'ar');

        $this->assertSame([], $response['actions']);
        $this->assertNull($response['workflow']);
        $this->assertStringContainsString('وعليكم السلام', $response['introduction']);
        $this->assertStringNotContainsString('التقديم', $response['text']);
        $this->assertStringNotContainsString('القبول', $response['explanation']);
    }

    public function test_english_hello_is_greeting_not_workflow(): void
    {
        $ctx = ['role' => 'guest', 'portal' => 'public', 'page_context' => 'home'];

        $this->assertSame('greeting', $this->intents->detect('Hello'));

        $response = $this->intents->respond('greeting', 'Hello', $ctx, 'en');

        $this->assertSame([], $response['actions']);
        $this->assertStringContainsString('Hello', $response['introduction']);
    }

    public function test_thank_you_intent(): void
    {
        $ctx = ['role' => 'guest', 'portal' => 'public', 'page_context' => 'home'];

        $this->assertSame('thank_you', $this->intents->detect('شكراً'));

        $response = $this->intents->respond('thank_you', 'شكراً', $ctx, 'ar');

        $this->assertSame('celebrating', $response['expression']);
        $this->assertSame([], $response['actions']);
    }

    public function test_how_do_i_apply_is_workflow(): void
    {
        $this->assertSame('workflow', $this->intents->detect('How do I apply?'));
        $this->assertSame('workflow', $this->intents->detect('كيف أقدّم للقبول؟'));
    }

    public function test_small_talk_intent(): void
    {
        $this->assertSame('small_talk', $this->intents->detect('كيف حالك؟'));

        $response = $this->intents->respond('small_talk', 'كيف حالك؟', ['role' => 'guest'], 'ar');

        $this->assertSame([], $response['actions']);
        $this->assertStringContainsString('بخير', $response['introduction']);
    }
}
