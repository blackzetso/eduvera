<?php

namespace Tests\Unit\Dova;

use App\Models\DovaKnowledgeQuery;
use App\Models\DovaVoiceRecognition;
use App\Services\Dova\DovaVoiceAnalyticsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DovaVoiceAnalyticsTest extends TestCase
{
    protected DovaVoiceAnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('dova_voice_recognitions');
        Schema::dropIfExists('dova_knowledge_queries');

        Schema::create('dova_knowledge_queries', function (Blueprint $table) {
            $table->id();
            $table->text('question');
            $table->string('normalized_question', 500)->nullable();
            $table->string('portal', 32)->default('public');
            $table->string('role', 32)->default('guest');
            $table->string('input_method', 16)->default('text');
            $table->string('detected_language', 8)->nullable();
            $table->boolean('answered')->default(false);
            $table->timestamps();
        });

        Schema::create('dova_voice_recognitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('portal', 32)->default('public');
            $table->string('role', 32)->default('guest');
            $table->boolean('success')->default(false);
            $table->string('engine', 32)->default('web_speech');
            $table->string('detected_language', 8)->nullable();
            $table->text('transcript')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
        });

        $this->service = new DovaVoiceAnalyticsService;
    }

    public function test_summary_counts_voice_and_text_questions(): void
    {
        DovaKnowledgeQuery::query()->create([
            'question' => 'Voice question',
            'normalized_question' => 'voice question',
            'input_method' => 'voice',
            'detected_language' => 'en',
            'answered' => true,
        ]);

        DovaKnowledgeQuery::query()->create([
            'question' => 'Text question',
            'normalized_question' => 'text question',
            'input_method' => 'text',
            'answered' => false,
        ]);

        DovaKnowledgeQuery::query()->create([
            'question' => 'عايز أعرف المصروفات',
            'normalized_question' => 'عايز أعرف المصروفات',
            'input_method' => 'voice',
            'detected_language' => 'ar',
            'answered' => true,
        ]);

        DovaVoiceRecognition::query()->create([
            'success' => true,
            'engine' => 'web_speech',
            'transcript' => 'Hello',
            'detected_language' => 'en',
            'duration_ms' => 4000,
        ]);

        DovaVoiceRecognition::query()->create([
            'success' => false,
            'engine' => 'web_speech',
            'error_code' => 'empty_transcript',
            'duration_ms' => 2000,
        ]);

        $summary = $this->service->summary();

        $this->assertSame(2, $summary['voiceQuestions']);
        $this->assertSame(1, $summary['textQuestions']);
        $this->assertSame(2, $summary['totalRecognitions']);
        $this->assertSame(1, $summary['successfulRecognitions']);
        $this->assertSame(50, $summary['recognitionSuccessRate']);
        $this->assertSame(50, $summary['recognitionFailureRate']);
        $this->assertSame(4000, $summary['averageRecordingDurationMs']);
        $this->assertSame('4s', $summary['averageRecordingDurationLabel']);
        $this->assertSame(1, $summary['arabicVsEnglish']['arabic']);
        $this->assertSame(1, $summary['arabicVsEnglish']['english']);
        $this->assertNotEmpty($summary['mostCommonVoiceQuestions']);
    }

    public function test_log_recognition_stores_transcript_not_audio(): void
    {
        $record = $this->service->logRecognition(
            success: true,
            engine: 'web_speech',
            context: ['portal' => 'public', 'role' => 'guest'],
            transcript: 'What are the school fees?',
            detectedLanguage: 'en',
            durationMs: 1500,
        );

        $this->assertDatabaseHas('dova_voice_recognitions', [
            'id' => $record->id,
            'success' => true,
            'engine' => 'web_speech',
            'transcript' => 'What are the school fees?',
            'detected_language' => 'en',
        ]);
    }
}
