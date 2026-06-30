<?php

namespace Tests\Unit\Dova;

use App\Services\Dova\DovaLLMService;
use App\Support\Dova\DovaPersonalityService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DovaLLMServiceTest extends TestCase
{
    protected DovaLLMService $llm;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'dova-ai.enabled' => true,
            'dova-ai.api_key' => 'test-key',
            'dova-ai.model' => 'gpt-4o-mini',
        ]);

        $this->llm = new DovaLLMService(new DovaPersonalityService);
    }

    public function test_falls_back_when_disabled(): void
    {
        config(['dova-ai.enabled' => false]);

        $result = $this->llm->enhanceKnowledge(
            'What is the school name?',
            [
                'introduction' => 'This school is Nile Private Schools.',
                'explanation' => '',
                'footer' => 'Footer text.',
                'source' => 'school_info',
            ],
            'en',
            ['portal' => 'public', 'role' => 'guest'],
        );

        $this->assertFalse($result['used_llm']);
        $this->assertTrue($result['fallback']);
        $this->assertStringContainsString('Nile Private Schools', $result['introduction']);
    }

    public function test_rewrites_knowledge_with_openai(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'introduction' => 'The name of this school is Nile Private Schools.',
                            'explanation' => 'Nile Private Schools is an international educational institution.',
                            'footer' => 'You can also explore related pages below.',
                        ]),
                    ],
                ]],
                'usage' => [
                    'prompt_tokens' => 120,
                    'completion_tokens' => 45,
                    'total_tokens' => 165,
                ],
            ], 200),
        ]);

        $result = $this->llm->enhanceKnowledge(
            'What is the name of this school?',
            [
                'introduction' => 'This school is Nile Private Schools.',
                'explanation' => 'It is International School.',
                'footer' => 'You can also use the suggestions below.',
                'source' => 'school_info',
                'record' => 'name',
                'confidence' => 0.97,
                'matchedText' => 'Nile Private Schools',
            ],
            'en',
            ['portal' => 'public', 'role' => 'guest'],
        );

        $this->assertTrue($result['used_llm']);
        $this->assertFalse($result['fallback']);
        $this->assertStringContainsString('Nile Private Schools', $result['introduction']);
    }

    public function test_falls_back_on_api_error(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response(['error' => 'quota'], 429),
        ]);

        $result = $this->llm->enhanceKnowledge(
            'School phone?',
            [
                'introduction' => 'Phone is +20 2 0000 0000.',
                'explanation' => '',
                'footer' => '',
            ],
            'en',
            ['portal' => 'public', 'role' => 'guest'],
        );

        $this->assertFalse($result['used_llm']);
        $this->assertTrue($result['fallback']);
        $this->assertStringContainsString('+20 2 0000 0000', $result['introduction']);
    }
}
