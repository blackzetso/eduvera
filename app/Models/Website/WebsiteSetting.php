<?php

namespace App\Models\Website;

use App\Services\Translation\BilingualAutoTranslationService;
use Illuminate\Database\Eloquent\Model;

class WebsiteSetting extends Model
{
    protected $fillable = ['key', 'value'];

    protected $casts = [
        'value' => 'array',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $row = static::query()->where('key', $key)->first();

        return $row?->value ?? $default;
    }

    public static function putValue(string $key, mixed $value, bool $translate = true): void
    {
        if ($translate && is_array($value)) {
            $value = app(BilingualAutoTranslationService::class)->translatePayload($value);
        }

        static::writeValue($key, $value);
    }

    public static function writeValue(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Persist immediately, then translate large payloads in the background.
     *
     * @param  array<string, mixed>  $value
     */
    public static function putValueAsync(string $key, array $value): void
    {
        /** @var BilingualAutoTranslationService $translator */
        $translator = app(BilingualAutoTranslationService::class);

        if ($translator->shouldUseQueue($value)) {
            static::writeValue($key, $value);
            \App\Jobs\TranslateBilingualContentJob::dispatch($key, $value);

            return;
        }

        static::putValue($key, $value);
    }
}
