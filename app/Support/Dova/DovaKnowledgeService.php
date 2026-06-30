<?php

namespace App\Support\Dova;

use App\Models\DovaFaq;
use App\Services\Dova\DovaFaqService;
use App\Services\Website\WebsiteContentService;
use App\Support\LocalizedContent;
use App\Support\Website\WebsiteDefaultsRepository;

/**
 * Answers factual questions from live website / CMS content (no OpenAI).
 */
class DovaKnowledgeService
{
    public function __construct(
        protected WebsiteContentService $websiteContent,
        protected DovaFaqService $dovaFaqs,
        protected DovaKnowledgeRetrievalNormalizer $retrieval,
    ) {}

    /**
     * @return array{
     *   matched: bool,
     *   confidence: float,
     *   source: string|null,
     *   record: string|null,
     *   introduction: string,
     *   explanation: string,
     *   footer: string,
     *   expression: string
     * }
     */
    public function answer(string $message, string $locale): array
    {
        $empty = $this->emptyResult();

        $analysis = $this->retrieval->analyze($message);
        $normalized = $analysis['normalized'];
        if ($normalized === '') {
            return $empty;
        }

        $isAr = $locale === 'ar' || $analysis['language'] === 'ar' || $analysis['language'] === 'mixed';
        $content = $this->loadContent($locale);
        $schoolInfo = $content['schoolInfo'] ?? [];
        $searchTerms = $analysis['search_terms'];

        $intent = $analysis['intent'];
        if ($intent !== null) {
            $resolved = $this->answerIntent($intent, $content, $schoolInfo, $isAr);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        $faq = $this->matchFaq($normalized, $content['faqs'] ?? [], $isAr, $searchTerms);
        if ($faq !== null) {
            return $faq;
        }

        $search = $this->searchCorpus($normalized, $content, $isAr, $searchTerms);
        if ($search !== null && $search['confidence'] >= 0.62) {
            return $search;
        }

        return $empty;
    }

    /**
     * @return array{
     *   matched: bool,
     *   confidence: float,
     *   source: string|null,
     *   record: string|null,
     *   introduction: string,
     *   explanation: string,
     *   footer: string,
     *   expression: string
     * }
     */
    protected function emptyResult(): array
    {
        return [
            'matched' => false,
            'confidence' => 0.0,
            'source' => null,
            'record' => null,
            'introduction' => '',
            'explanation' => '',
            'footer' => '',
            'expression' => 'explaining',
        ];
    }

    protected function normalize(string $message): string
    {
        return $this->retrieval->normalize($message);
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadContent(string $locale): array
    {
        if ($this->websiteContent->isCmsActive()) {
            $content = $this->websiteContent->forLanding(false);
        } else {
            $defaults = WebsiteDefaultsRepository::load();
            $content = LocalizedContent::resolve($defaults, $locale);
        }

        return $this->mergePublishedFaqs($content, $locale);
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    protected function mergePublishedFaqs(array $content, string $locale): array
    {
        $managed = $this->dovaFaqs->publishedForKnowledge($locale);
        if ($managed !== []) {
            $content['faqs'] = array_merge($content['faqs'] ?? [], $managed);
        }

        return $content;
    }

    /**
     * @param  array{intent: string, confidence: float}  $intent
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>  $schoolInfo
     * @return array<string, mixed>|null
     */
    protected function answerIntent(array $intent, array $content, array $schoolInfo, bool $isAr): ?array
    {
        return match ($intent['intent']) {
            'school_name' => $this->answerSchoolName($schoolInfo, $intent['confidence'], $isAr),
            'school_phone' => $this->answerSchoolPhone($schoolInfo, $intent['confidence'], $isAr),
            'school_email' => $this->answerSchoolEmail($schoolInfo, $intent['confidence'], $isAr),
            'school_address' => $this->answerSchoolAddress($schoolInfo, $intent['confidence'], $isAr),
            'school_hours' => $this->answerSchoolHours($schoolInfo, $intent['confidence'], $isAr),
            'admission_requirements' => $this->answerAdmissionRequirements($content, $intent['confidence'], $isAr),
            'programs' => $this->answerPrograms($content, $intent['confidence'], $isAr),
            'admissions_contact' => $this->answerAdmissionsContact($schoolInfo, $content, $intent['confidence'], $isAr),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $schoolInfo
     */
    protected function answerSchoolName(array $schoolInfo, float $confidence, bool $isAr): ?array
    {
        $name = trim((string) ($schoolInfo['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $tagline = trim((string) ($schoolInfo['tagline'] ?? ''));

        return $this->buildResult(
            source: 'school_info',
            record: 'name',
            confidence: $confidence,
            introduction: $isAr ? "هذه المدرسة هي {$name}." : "This school is {$name}.",
            explanation: $tagline !== ''
                ? ($isAr ? "وهي {$tagline}." : "It is {$tagline}.")
                : '',
            footer: $this->footerForActions($isAr),
            matchedText: $name,
        );
    }

    /**
     * @param  array<string, mixed>  $schoolInfo
     */
    protected function answerSchoolPhone(array $schoolInfo, float $confidence, bool $isAr): ?array
    {
        $phone = $this->contactValue($schoolInfo, 'phone');
        if ($phone === null) {
            return null;
        }

        $hours = $this->contactValue($schoolInfo, 'hours');

        return $this->buildResult(
            source: 'school_info.contact',
            record: 'phone',
            confidence: $confidence,
            introduction: $isAr
                ? "رقم هاتف المدرسة هو {$phone}."
                : "The school phone number is {$phone}.",
            explanation: $hours
                ? ($isAr ? "ساعات العمل: {$hours}." : "Office hours: {$hours}.")
                : '',
            footer: $this->footerForActions($isAr),
            matchedText: $phone,
        );
    }

    /**
     * @param  array<string, mixed>  $schoolInfo
     */
    protected function answerSchoolEmail(array $schoolInfo, float $confidence, bool $isAr): ?array
    {
        $email = $this->contactValue($schoolInfo, 'email');
        if ($email === null) {
            return null;
        }

        return $this->buildResult(
            source: 'school_info.contact',
            record: 'email',
            confidence: $confidence,
            introduction: $isAr
                ? "البريد الإلكتروني للمدرسة هو {$email}."
                : "The school email address is {$email}.",
            explanation: '',
            footer: $this->footerForActions($isAr),
            matchedText: $email,
        );
    }

    /**
     * @param  array<string, mixed>  $schoolInfo
     */
    protected function answerSchoolAddress(array $schoolInfo, float $confidence, bool $isAr): ?array
    {
        $address = $this->contactValue($schoolInfo, 'address');
        if ($address === null) {
            return null;
        }

        return $this->buildResult(
            source: 'school_info.contact',
            record: 'address',
            confidence: $confidence,
            introduction: $isAr
                ? "عنوان المدرسة: {$address}."
                : "The school is located at {$address}.",
            explanation: '',
            footer: $this->footerForActions($isAr),
            matchedText: $address,
        );
    }

    /**
     * @param  array<string, mixed>  $schoolInfo
     */
    protected function answerSchoolHours(array $schoolInfo, float $confidence, bool $isAr): ?array
    {
        $hours = $this->contactValue($schoolInfo, 'hours');
        if ($hours === null) {
            return null;
        }

        return $this->buildResult(
            source: 'school_info.contact',
            record: 'hours',
            confidence: $confidence,
            introduction: $isAr
                ? "ساعات عمل المدرسة: {$hours}."
                : "School hours are {$hours}.",
            explanation: '',
            footer: $this->footerForActions($isAr),
            matchedText: $hours,
        );
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected function answerAdmissionRequirements(array $content, float $confidence, bool $isAr): ?array
    {
        $lines = [];

        foreach ($content['admissionSteps'] ?? [] as $step) {
            $title = trim((string) ($step['title'] ?? ''));
            $text = trim((string) ($step['text'] ?? ''));
            if ($title !== '' && $text !== '') {
                $lines[] = "{$title}: {$text}";
            }
        }

        foreach ($content['stages'] ?? [] as $stage) {
            $admission = $stage['admission'] ?? [];
            if (! is_array($admission) || $admission === []) {
                continue;
            }

            $stageTitle = trim((string) ($stage['title'] ?? ''));
            foreach ($admission as $item) {
                $item = trim((string) $item);
                if ($item !== '') {
                    $prefix = $stageTitle !== '' ? "{$stageTitle} — " : '';
                    $lines[] = $prefix.$item;
                }
            }
        }

        $documents = $content['admissionDocuments'] ?? [];
        if (is_array($documents)) {
            foreach ($documents as $doc) {
                if (is_string($doc) && trim($doc) !== '') {
                    $lines[] = trim($doc);
                } elseif (is_array($doc)) {
                    $label = trim((string) ($doc['label'] ?? $doc['title'] ?? $doc['name'] ?? ''));
                    if ($label !== '') {
                        $lines[] = $label;
                    }
                }
            }
        }

        if ($lines === []) {
            $faq = $this->findFaqByCategory($content['faqs'] ?? [], 'admissions', $isAr);
            if ($faq !== null) {
                return $faq;
            }

            return null;
        }

        $unique = array_values(array_unique($lines));
        $summary = implode("\n", array_map(fn ($line) => "• {$line}", array_slice($unique, 0, 8)));

        return $this->buildResult(
            source: 'admission_steps',
            record: 'requirements',
            confidence: $confidence,
            introduction: $isAr
                ? 'متطلبات وخطوات القبول في المدرسة هي:'
                : 'Here are the admission requirements and steps published on the website:',
            explanation: $summary,
            footer: $this->footerForActions($isAr),
            matchedText: $unique[0] ?? null,
        );
    }

    /**
     * @param  array<string, mixed>  $content
     */
    protected function answerPrograms(array $content, float $confidence, bool $isAr): ?array
    {
        $programs = [];

        foreach ($content['stages'] ?? [] as $stage) {
            $title = trim((string) ($stage['title'] ?? ''));
            $subtitle = trim((string) ($stage['subtitle'] ?? $stage['ageRange'] ?? ''));
            if ($title === '') {
                continue;
            }

            $programs[] = $subtitle !== '' ? "{$title} ({$subtitle})" : $title;
        }

        foreach ($content['academicPrograms'] ?? [] as $program) {
            $title = trim((string) ($program['title'] ?? ''));
            $text = trim((string) ($program['text'] ?? ''));
            if ($title !== '') {
                $programs[] = $text !== '' ? "{$title}: {$text}" : $title;
            }
        }

        if ($programs === []) {
            return null;
        }

        $unique = array_values(array_unique($programs));
        $summary = implode("\n", array_map(fn ($line) => "• {$line}", array_slice($unique, 0, 10)));

        return $this->buildResult(
            source: 'academic_programs',
            record: 'programs',
            confidence: $confidence,
            introduction: $isAr
                ? 'البرامج والمراحل الدراسية المتاحة في المدرسة:'
                : 'These are the academic programs and stages offered by the school:',
            explanation: $summary,
            footer: $this->footerForActions($isAr),
            matchedText: $unique[0] ?? null,
        );
    }

    /**
     * @param  array<string, mixed>  $schoolInfo
     * @param  array<string, mixed>  $content
     */
    protected function answerAdmissionsContact(array $schoolInfo, array $content, float $confidence, bool $isAr): ?array
    {
        $email = $this->contactValue($schoolInfo, 'email');
        $phone = $this->contactValue($schoolInfo, 'phone');
        $whatsapp = $this->contactValue($schoolInfo, 'whatsapp');

        foreach ($content['whatsappQuickActions'] ?? [] as $action) {
            if (mb_stripos((string) ($action['text'] ?? ''), 'admission') !== false) {
                $whatsapp = $whatsapp ?? ($action['phone'] ?? null);
            }
        }

        $parts = array_filter([
            $email ? ($isAr ? "البريد: {$email}" : "Email: {$email}") : null,
            $phone ? ($isAr ? "الهاتف: {$phone}" : "Phone: {$phone}") : null,
            $whatsapp ? ($isAr ? "واتساب: {$whatsapp}" : "WhatsApp: {$whatsapp}") : null,
        ]);

        if ($parts === []) {
            return null;
        }

        return $this->buildResult(
            source: 'school_info.contact',
            record: 'admissions_contact',
            confidence: $confidence,
            introduction: $isAr
                ? 'للتواصل مع فريق القبول:'
                : 'For admissions enquiries, contact the school using:',
            explanation: implode("\n", $parts),
            footer: $this->footerForActions($isAr),
            matchedText: $email ?? $phone,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $faqs
     * @return array<string, mixed>|null
     */
    protected function matchFaq(string $normalized, array $faqs, bool $isAr, array $searchTerms = []): ?array
    {
        $trusted = $this->bestFaqMatch($normalized, $faqs, $searchTerms, [
            DovaFaq::KNOWLEDGE_ACTIVE,
            DovaFaq::KNOWLEDGE_NEEDS_REVIEW,
        ]);

        if ($trusted !== null) {
            return $this->buildFaqResult($trusted, $isAr);
        }

        $deprecated = $this->bestFaqMatch($normalized, $faqs, $searchTerms, [
            DovaFaq::KNOWLEDGE_DEPRECATED,
        ]);

        if ($deprecated !== null) {
            return $this->buildFaqResult($deprecated, $isAr);
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $faqs
     * @param  array<int, string>  $allowedStatuses
     * @return array{index: int, question: string, answer: string, category: string, dova_id: int|null, score: float, knowledge_status: string}|null
     */
    protected function bestFaqMatch(string $normalized, array $faqs, array $searchTerms, array $allowedStatuses): ?array
    {
        $best = null;
        $bestScore = 0.0;

        foreach ($faqs as $index => $faq) {
            $knowledgeStatus = (string) ($faq['knowledge_status'] ?? DovaFaq::KNOWLEDGE_ACTIVE);

            if ($knowledgeStatus === DovaFaq::KNOWLEDGE_ARCHIVED) {
                continue;
            }

            if (! in_array($knowledgeStatus, $allowedStatuses, true)) {
                continue;
            }

            $question = $this->normalize((string) ($faq['q'] ?? ''));
            $answer = trim((string) ($faq['a'] ?? ''));
            if ($question === '' || $answer === '') {
                continue;
            }

            $score = $this->similarityScore($normalized, $question, $searchTerms);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [
                    'index' => $index,
                    'question' => (string) ($faq['q'] ?? ''),
                    'answer' => $answer,
                    'category' => (string) ($faq['cat'] ?? 'faq'),
                    'dova_id' => $faq['dova_id'] ?? null,
                    'score' => $score,
                    'knowledge_status' => $knowledgeStatus,
                ];
            }
        }

        if ($best === null || $bestScore < 0.55) {
            return null;
        }

        return $best;
    }

    /**
     * @param  array{index: int, question: string, answer: string, category: string, dova_id: int|null, score: float, knowledge_status: string}  $best
     */
    protected function buildFaqResult(array $best, bool $isAr): array
    {
        $dovaId = $best['dova_id'] ?? null;
        if ($dovaId) {
            DovaFaq::query()->where('id', $dovaId)->increment('view_count');
        }

        $multiplier = config("dova-knowledge-governance.confidence_multipliers.{$best['knowledge_status']}", 1.0);
        $confidence = min(0.92, (0.65 + $best['score'] * 0.35) * $multiplier);

        return $this->buildResult(
            source: $dovaId ? 'dova_faq' : 'faqs',
            record: $dovaId ? "dova.{$dovaId}" : "faq.{$best['index']}",
            confidence: $confidence,
            introduction: $isAr
                ? ($dovaId ? 'وفقاً لقاعدة معرفة دوفا:' : 'وفقاً لقسم الأسئلة الشائعة في الموقع:')
                : ($dovaId ? 'From the Dova knowledge base:' : 'According to the website FAQ:'),
            explanation: $best['answer'],
            footer: $this->footerForActions($isAr),
            matchedText: $best['question'],
            faqId: $dovaId,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $faqs
     */
    protected function findFaqByCategory(array $faqs, string $category, bool $isAr): ?array
    {
        foreach ($faqs as $index => $faq) {
            if (mb_stripos((string) ($faq['cat'] ?? ''), $category) === false) {
                continue;
            }

            $answer = trim((string) ($faq['a'] ?? ''));
            if ($answer === '') {
                continue;
            }

            return $this->buildResult(
                source: 'faqs',
                record: "faq.{$index}",
                confidence: 0.82,
                introduction: $isAr ? 'من قسم الأسئلة الشائعة:' : 'From the website FAQ:',
                explanation: $answer,
                footer: $this->footerForActions($isAr),
                matchedText: (string) ($faq['q'] ?? ''),
            );
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>|null
     */
    protected function searchCorpus(string $normalized, array $content, bool $isAr, array $searchTerms = []): ?array
    {
        $records = $this->buildCorpus($content);
        $best = null;
        $bestScore = 0.0;

        foreach ($records as $record) {
            $score = $this->similarityScore($normalized, $record['searchText'], $searchTerms);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $record;
            }
        }

        if ($best === null || $bestScore < 0.62) {
            return null;
        }

        return $this->buildResult(
            source: $best['source'],
            record: $best['record'],
            confidence: min(0.88, 0.55 + $bestScore * 0.4),
            introduction: $best['introduction'][$isAr ? 'ar' : 'en'] ?? $best['introduction']['en'] ?? '',
            explanation: $best['explanation'][$isAr ? 'ar' : 'en'] ?? $best['explanation']['en'] ?? '',
            footer: $this->footerForActions($isAr),
            matchedText: $best['matchedText'] ?? null,
        );
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<int, array<string, mixed>>
     */
    protected function buildCorpus(array $content): array
    {
        $schoolInfo = $content['schoolInfo'] ?? [];
        $name = (string) ($schoolInfo['name'] ?? '');
        $records = [];

        if ($name !== '') {
            $records[] = [
                'source' => 'school_info',
                'record' => 'name',
                'searchText' => "school name {$name}",
                'matchedText' => $name,
                'introduction' => [
                    'en' => "This school is {$name}.",
                    'ar' => "هذه المدرسة هي {$name}.",
                ],
                'explanation' => ['en' => '', 'ar' => ''],
            ];
        }

        $hero = $schoolInfo['hero'] ?? [];
        $headline = trim(implode(' ', array_filter([
            $hero['headlineLine1'] ?? '',
            $hero['headlineAccent'] ?? '',
            $hero['headlineLine2'] ?? '',
        ])));
        if ($headline !== '') {
            $subheadline = (string) ($hero['subheadline'] ?? '');
            $records[] = [
                'source' => 'school_info.hero',
                'record' => 'headline',
                'searchText' => "hero headline {$headline} {$subheadline}",
                'matchedText' => $headline,
                'introduction' => [
                    'en' => $headline,
                    'ar' => $headline,
                ],
                'explanation' => [
                    'en' => (string) ($hero['subheadline'] ?? ''),
                    'ar' => (string) ($hero['subheadline'] ?? ''),
                ],
            ];
        }

        foreach ($content['navLinks'] ?? [] as $index => $link) {
            $label = trim((string) ($link['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $records[] = [
                'source' => 'nav_links',
                'record' => "nav.{$index}",
                'searchText' => "navigation {$label}",
                'matchedText' => $label,
                'introduction' => [
                    'en' => "You can find «{$label}» in the website navigation.",
                    'ar' => "يمكنك العثور على «{$label}» في قائمة الموقع.",
                ],
                'explanation' => ['en' => '', 'ar' => ''],
            ];
        }

        foreach ($content['newsItems'] ?? [] as $index => $post) {
            $title = trim((string) ($post['title'] ?? ''));
            $excerpt = trim((string) ($post['excerpt'] ?? $post['summary'] ?? ''));
            if ($title === '') {
                continue;
            }

            $records[] = [
                'source' => 'news',
                'record' => "news.{$index}",
                'searchText' => "news {$title} {$excerpt}",
                'matchedText' => $title,
                'introduction' => ['en' => $title, 'ar' => $title],
                'explanation' => ['en' => $excerpt, 'ar' => $excerpt],
            ];
        }

        foreach ($content['events'] ?? [] as $index => $event) {
            $title = trim((string) ($event['title'] ?? ''));
            $date = trim((string) ($event['date'] ?? ''));
            if ($title === '') {
                continue;
            }

            $records[] = [
                'source' => 'events',
                'record' => "event.{$index}",
                'searchText' => "event {$title} {$date}",
                'matchedText' => $title,
                'introduction' => ['en' => $title, 'ar' => $title],
                'explanation' => ['en' => $date, 'ar' => $date],
            ];
        }

        return $records;
    }

    protected function similarityScore(string $query, string $candidate, array $searchTerms = []): float
    {
        $query = $this->normalize($query);
        $candidate = $this->normalize($candidate);

        if ($query === '' || $candidate === '') {
            return 0.0;
        }

        if ($query === $candidate || str_contains($candidate, $query) || str_contains($query, $candidate)) {
            return 1.0;
        }

        $queries = array_values(array_unique(array_filter([$query, ...$searchTerms])));
        $best = 0.0;

        foreach ($queries as $q) {
            $q = $this->normalize($q);
            if ($q === '') {
                continue;
            }

            if (str_contains($candidate, $q) || str_contains($q, $candidate)) {
                $best = max($best, 0.95);
                continue;
            }

            $queryTokens = array_values(array_filter(
                explode(' ', $q),
                fn ($t) => mb_strlen($t) > 1,
            ));

            if ($queryTokens === []) {
                continue;
            }

            $hits = 0;
            foreach ($queryTokens as $token) {
                if (str_contains($candidate, $token)) {
                    $hits++;
                }
            }

            $best = max($best, $hits / count($queryTokens));
        }

        return $best;
    }

    /**
     * @param  array<string, mixed>  $schoolInfo
     */
    protected function contactValue(array $schoolInfo, string $key): ?string
    {
        $contact = $schoolInfo['contact'][$key] ?? null;
        if (is_string($contact) && trim($contact) !== '') {
            return trim($contact);
        }

        $topBar = $schoolInfo['topBar'][$key] ?? null;
        if (is_string($topBar) && trim($topBar) !== '') {
            return trim($topBar);
        }

        return null;
    }

    protected function footerForActions(bool $isAr): string
    {
        return $isAr
            ? 'يمكنك أيضاً استخدام الاقتراحات أدناه للانتقال إلى صفحات ذات صلة.'
            : 'You can also use the suggestions below to jump to related pages.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildResult(
        string $source,
        string $record,
        float $confidence,
        string $introduction,
        string $explanation,
        string $footer,
        mixed $matchedText = null,
        ?int $faqId = null,
    ): array {
        return [
            'matched' => true,
            'confidence' => round($confidence, 2),
            'source' => $source,
            'record' => $record,
            'faqId' => $faqId,
            'matchedText' => is_string($matchedText) ? $matchedText : null,
            'introduction' => $introduction,
            'explanation' => $explanation,
            'footer' => $footer,
            'expression' => 'explaining',
        ];
    }
}
