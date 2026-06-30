<?php

namespace App\Models\Concerns;

use App\Observers\BilingualContentObserver;

trait AutoTranslatesBilingualFields
{
    public static function bootAutoTranslatesBilingualFields(): void
    {
        static::observe(BilingualContentObserver::class);
    }
}
