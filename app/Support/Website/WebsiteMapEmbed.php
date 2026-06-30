<?php

namespace App\Support\Website;

final class WebsiteMapEmbed
{
    /**
     * Accepts a bare Google Maps embed URL or full <iframe> HTML from "Share → Embed".
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $value = trim($value);

        if (preg_match('/src\s*=\s*["\']([^"\']+)["\']/i', $value, $matches)) {
            return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        }

        if (preg_match('#https?://[^\s"\'<>]+#i', $value, $matches)) {
            return $matches[0];
        }

        return $value;
    }

    public static function normalizeSchoolInfo(array $schoolInfo): array
    {
        if (isset($schoolInfo['contact']['mapEmbedUrl'])) {
            $schoolInfo['contact']['mapEmbedUrl'] = static::normalize($schoolInfo['contact']['mapEmbedUrl']);
        }

        return $schoolInfo;
    }
}
