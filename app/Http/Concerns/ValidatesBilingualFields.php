<?php

namespace App\Http\Concerns;

trait ValidatesBilingualFields
{
    /**
     * @return array<string, string>
     */
    protected function bilingualFieldRules(string $base, int $maxLength = 255, bool $multiline = false): array
    {
        $type = $multiline ? 'string' : 'string';
        $max = $multiline ? '' : "|max:{$maxLength}";

        return [
            $base => "nullable|{$type}{$max}|required_without:{$base}_ar",
            "{$base}_ar" => "nullable|{$type}{$max}|required_without:{$base}",
        ];
    }
}
