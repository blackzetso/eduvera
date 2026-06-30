<?php

namespace Tests\Unit\Dova;

use App\Services\Dova\DovaFaqService;
use App\Services\Website\WebsiteContentService;
use App\Support\Dova\DovaKnowledgeRetrievalNormalizer;
use App\Support\Dova\DovaKnowledgeService;
use Mockery;
use Tests\TestCase;

class DovaKnowledgeServiceTest extends TestCase
{
    protected DovaKnowledgeService $knowledge;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockWebsiteContent($this->fixtureContent());

        $faqMock = Mockery::mock(DovaFaqService::class);
        $faqMock->shouldReceive('publishedForKnowledge')->andReturn([]);
        $this->app->instance(DovaFaqService::class, $faqMock);

        $this->knowledge = new DovaKnowledgeService(
            app(WebsiteContentService::class),
            app(DovaFaqService::class),
            new DovaKnowledgeRetrievalNormalizer,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function fixtureContent(): array
    {
        return [
            'schoolInfo' => [
                'name' => 'Nile Private Schools',
                'tagline' => 'International School',
                'contact' => [
                    'address' => '12 Nile Corniche, Cairo',
                    'phone' => '+20 2 1234 5678',
                    'whatsapp' => '+20 100 111 2222',
                    'email' => 'admissions@nile.edu',
                    'hours' => 'Sun–Thu 7:30 AM – 3:30 PM',
                ],
                'topBar' => [
                    'phone' => '+20 2 1234 5678',
                    'email' => 'admissions@nile.edu',
                ],
            ],
            'admissionSteps' => [
                ['step' => 1, 'title' => 'Inquiry', 'text' => 'Submit interest form or book a campus tour.'],
                ['step' => 2, 'title' => 'Application', 'text' => 'Complete online application with documents.'],
                ['step' => 3, 'title' => 'Assessment', 'text' => 'Age-appropriate evaluation and records review.'],
            ],
            'stages' => [
                [
                    'id' => 'primary',
                    'title' => 'Primary',
                    'subtitle' => 'Ages 6–11',
                    'admission' => ['Previous school records', 'Birth certificate'],
                ],
                [
                    'id' => 'secondary',
                    'title' => 'Secondary',
                    'subtitle' => 'Ages 12–17',
                    'admission' => ['Entrance assessment', 'Recommendation letter'],
                ],
            ],
            'academicPrograms' => [
                ['title' => 'Cambridge Curriculum', 'text' => 'IGCSE pathway with local accreditation.'],
                ['title' => 'STEM Track', 'text' => 'Robotics, coding, and science enrichment.'],
            ],
            'faqs' => [
                [
                    'q' => 'When do admissions open?',
                    'a' => 'Admissions for 2026–2027 are open year-round.',
                    'cat' => 'Admissions',
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected function mockWebsiteContent(array $content): void
    {
        $mock = Mockery::mock(WebsiteContentService::class);
        $mock->shouldReceive('isCmsActive')->andReturn(true);
        $mock->shouldReceive('forLanding')->with(false)->andReturn($content);

        $this->app->instance(WebsiteContentService::class, $mock);
    }

    public function test_answers_school_name_from_cms(): void
    {
        $result = $this->knowledge->answer('What is the name of this school?', 'en');

        $this->assertTrue($result['matched']);
        $this->assertSame('school_info', $result['source']);
        $this->assertSame('name', $result['record']);
        $this->assertGreaterThan(0.9, $result['confidence']);
        $this->assertStringContainsString('Nile Private Schools', $result['introduction']);
    }

    public function test_answers_school_phone_from_cms(): void
    {
        $result = $this->knowledge->answer('What is the school phone number?', 'en');

        $this->assertTrue($result['matched']);
        $this->assertSame('school_info.contact', $result['source']);
        $this->assertStringContainsString('+20 2 1234 5678', $result['introduction']);
    }

    public function test_answers_admission_requirements_from_cms(): void
    {
        $result = $this->knowledge->answer('What are the admission requirements?', 'en');

        $this->assertTrue($result['matched']);
        $this->assertSame('admission_steps', $result['source']);
        $this->assertStringContainsString('Inquiry', $result['explanation']);
        $this->assertStringContainsString('Birth certificate', $result['explanation']);
    }

    public function test_answers_programs_from_cms(): void
    {
        $result = $this->knowledge->answer('What programs do you offer?', 'en');

        $this->assertTrue($result['matched']);
        $this->assertSame('academic_programs', $result['source']);
        $this->assertStringContainsString('Primary', $result['explanation']);
        $this->assertStringContainsString('Cambridge Curriculum', $result['explanation']);
    }

    public function test_answers_admissions_contact_from_cms(): void
    {
        $result = $this->knowledge->answer('Who can I contact for admissions?', 'en');

        $this->assertTrue($result['matched']);
        $this->assertSame('school_info.contact', $result['source']);
        $this->assertStringContainsString('admissions@nile.edu', $result['explanation']);
        $this->assertStringContainsString('+20 2 1234 5678', $result['explanation']);
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    public static function multilingualAdmissionsContactProvider(): array
    {
        return [
            ['How can I contact admissions?', 'en'],
            ['Admissions contact', 'en'],
            ['Admissions phone number', 'en'],
            ['Contact admissions', 'en'],
            ['What is the admissions email?', 'en'],
            ['كيف أتواصل مع القبول؟', 'ar'],
            ['رقم القبول؟', 'ar'],
            ['أريد التواصل مع القبول', 'ar'],
            ['كيف أكلم إدارة القبول؟', 'ar'],
            ['بريد القبول', 'ar'],
            ['واتساب القبول', 'ar'],
        ];
    }

    /**
     * @dataProvider multilingualAdmissionsContactProvider
     */
    public function test_multilingual_admissions_contact_queries_share_same_source(string $question, string $locale): void
    {
        $result = $this->knowledge->answer($question, $locale);

        $this->assertTrue($result['matched'], "Failed for: {$question}");
        $this->assertSame('school_info.contact', $result['source'], "Wrong source for: {$question}");
        $this->assertSame('admissions_contact', $result['record'], "Wrong record for: {$question}");
        $this->assertStringContainsString('admissions@nile.edu', $result['explanation']);
    }
}
