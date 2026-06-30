<?php

namespace Tests\Unit\Translation;

use App\Services\Translation\BilingualFieldResolver;
use Tests\TestCase;

class BilingualFieldResolverTest extends TestCase
{
    public function test_detects_en_to_ar_pair(): void
    {
        $resolver = new BilingualFieldResolver;

        $pairs = $resolver->pairsFromFlatArray([
            'title' => 'Hello',
            'title_ar' => '',
        ]);

        $this->assertCount(1, $pairs);
        $this->assertSame('en_to_ar', $pairs[0]['direction']);
        $this->assertSame('title', $pairs[0]['base']);
        $this->assertSame('title_ar', $pairs[0]['arabic']);
    }

    public function test_detects_ar_to_en_pair(): void
    {
        $resolver = new BilingualFieldResolver;

        $pairs = $resolver->pairsFromFlatArray([
            'text' => '',
            'text_ar' => 'مرحبا',
        ]);

        $this->assertCount(1, $pairs);
        $this->assertSame('ar_to_en', $pairs[0]['direction']);
    }

    public function test_supports_name_en_and_name_ar_pairs(): void
    {
        $resolver = new BilingualFieldResolver;

        $pairs = $resolver->pairsFromFlatArray([
            'name_en' => 'Admission Form',
            'name_ar' => '',
        ]);

        $this->assertCount(1, $pairs);
        $this->assertSame('name_en', $pairs[0]['base']);
        $this->assertSame('en_to_ar', $pairs[0]['direction']);
    }

    public function test_skips_configured_fields(): void
    {
        config(['translation.skip_fields' => ['slug']]);

        $resolver = new BilingualFieldResolver;

        $pairs = $resolver->pairsFromFlatArray([
            'slug' => 'news-item',
            'slug_ar' => '',
        ]);

        $this->assertSame([], $pairs);
    }
}
