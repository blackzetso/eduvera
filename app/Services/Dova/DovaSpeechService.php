<?php

namespace App\Services\Dova;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DovaSpeechService
{
    public function isWhisperAvailable(): bool
    {
        return filled(config('dova-ai.api_key'));
    }

    /**
     * Transcribe audio via OpenAI Whisper. Audio is not persisted.
     *
     * @return array{success: bool, transcript: ?string, detected_language: ?string, error: ?string}
     */
    public function transcribe(UploadedFile $audio, ?string $hintLanguage = null): array
    {
        if (! $this->isWhisperAvailable()) {
            return [
                'success' => false,
                'transcript' => null,
                'detected_language' => null,
                'error' => 'whisper_unavailable',
            ];
        }

        $maxMb = (int) config('dova-ai.whisper_max_file_mb', 10);
        if ($audio->getSize() > $maxMb * 1024 * 1024) {
            return [
                'success' => false,
                'transcript' => null,
                'detected_language' => null,
                'error' => 'file_too_large',
            ];
        }

        try {
            $request = Http::timeout((int) config('dova-ai.timeout_seconds', 25))
                ->withToken(config('dova-ai.api_key'))
                ->attach('file', fopen($audio->getRealPath(), 'r'), $audio->getClientOriginalName() ?: 'audio.webm')
                ->post(rtrim(config('dova-ai.base_url'), '/').'/audio/transcriptions', [
                    'model' => config('dova-ai.whisper_model', 'whisper-1'),
                    'response_format' => 'verbose_json',
                ]);

            if (! $request->successful()) {
                Log::warning('Dova Whisper transcription failed', [
                    'status' => $request->status(),
                    'body' => $request->body(),
                ]);

                return [
                    'success' => false,
                    'transcript' => null,
                    'detected_language' => null,
                    'error' => 'transcription_failed',
                ];
            }

            $json = $request->json();
            $transcript = trim((string) ($json['text'] ?? ''));
            $language = $this->normalizeLanguage(
                (string) ($json['language'] ?? $hintLanguage ?? $this->detectLanguageFromText($transcript))
            );

            if ($transcript === '') {
                return [
                    'success' => false,
                    'transcript' => null,
                    'detected_language' => $language,
                    'error' => 'empty_transcript',
                ];
            }

            return [
                'success' => true,
                'transcript' => $transcript,
                'detected_language' => $language,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Dova Whisper transcription exception', ['message' => $e->getMessage()]);

            return [
                'success' => false,
                'transcript' => null,
                'detected_language' => null,
                'error' => 'transcription_failed',
            ];
        }
    }

    public function detectLanguageFromText(string $text): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        if (preg_match('/[\x{0600}-\x{06FF}]/u', $text)) {
            return 'ar';
        }

        if (preg_match('/[a-zA-Z]/', $text)) {
            return 'en';
        }

        return null;
    }

    public function normalizeLanguage(?string $language): ?string
    {
        if ($language === null || $language === '') {
            return null;
        }

        $language = strtolower($language);

        if (str_starts_with($language, 'ar')) {
            return 'ar';
        }

        if (str_starts_with($language, 'en')) {
            return 'en';
        }

        return strlen($language) <= 8 ? $language : null;
    }
}
