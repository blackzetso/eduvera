<?php

namespace Tests\Unit\Dova;

use App\Support\Dova\DovaKnowledgeRetrievalNormalizer;
use Tests\TestCase;

class DovaKnowledgeRetrievalNormalizerTest extends TestCase
{
    protected DovaKnowledgeRetrievalNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new DovaKnowledgeRetrievalNormalizer;
    }

    public function test_detects_arabic_admissions_contact_intent(): void
    {
        $analysis = $this->normalizer->analyze('كيف أتواصل مع القبول؟');

        $this->assertSame('ar', $analysis['language']);
        $this->assertNotNull($analysis['intent']);
        $this->assertSame('admissions_contact', $analysis['intent']['intent']);
    }

    public function test_detects_english_admissions_contact_intent(): void
    {
        $analysis = $this->normalizer->analyze('How can I contact admissions?');

        $this->assertSame('en', $analysis['language']);
        $this->assertSame('admissions_contact', $analysis['intent']['intent']);
    }

    public function test_expands_admissions_synonyms_across_languages(): void
    {
        $expanded = $this->normalizer->expandSynonyms($this->normalizer->normalize('رقم القبول'));

        $this->assertStringContainsString('admission', $expanded);
        $this->assertStringContainsString('قبول', $expanded);
    }

    public function test_arabic_script_normalization_unifies_alef_variants(): void
    {
        $a = $this->normalizer->normalize('أتواصل');
        $b = $this->normalizer->normalize('اتواصل');

        $this->assertSame($a, $b);
    }
}
