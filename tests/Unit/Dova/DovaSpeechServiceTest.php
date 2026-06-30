<?php

namespace Tests\Unit\Dova;

use App\Services\Dova\DovaSpeechService;
use Tests\TestCase;

class DovaSpeechServiceTest extends TestCase
{
    protected DovaSpeechService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DovaSpeechService;
    }

    public function test_detects_arabic_from_text(): void
    {
        $this->assertSame('ar', $this->service->detectLanguageFromText('السلام عليكم'));
    }

    public function test_detects_english_from_text(): void
    {
        $this->assertSame('en', $this->service->detectLanguageFromText('How do I apply?'));
    }

    public function test_normalizes_language_codes(): void
    {
        $this->assertSame('ar', $this->service->normalizeLanguage('ar-SA'));
        $this->assertSame('en', $this->service->normalizeLanguage('en-US'));
        $this->assertNull($this->service->normalizeLanguage(''));
    }

    public function test_whisper_unavailable_without_api_key(): void
    {
        config(['dova-ai.api_key' => null]);

        $this->assertFalse($this->service->isWhisperAvailable());
    }

    public function test_whisper_available_with_api_key(): void
    {
        config(['dova-ai.api_key' => 'test-key']);

        $this->assertTrue($this->service->isWhisperAvailable());
    }
}
