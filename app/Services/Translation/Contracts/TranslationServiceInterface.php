<?php

namespace App\Services\Translation\Contracts;

interface TranslationServiceInterface
{
    /**
     * @param  array<string, string>  $segments  keyed segment id => source text
     * @return array<string, string|null>  keyed segment id => translated text
     */
    public function translateBatch(array $segments, string $from, string $to): array;

    public function isConfigured(): bool;
}
