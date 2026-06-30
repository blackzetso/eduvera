<?php

namespace App\Services\Dova;

use App\Models\DovaFaq;
use App\Support\LocalizedContent;
use App\Support\Website\WebsiteDefaultsRepository;

class DovaKnowledgeIndexBuilder
{
    /**
     * @return array<int, array{source_slug: string, record_key: string, title: string|null, content: string, locale: string}>
     */
    public function build(array $rawContent): array
    {
        $records = [];

        foreach (['en', 'ar'] as $locale) {
            $content = LocalizedContent::resolve($rawContent, $locale);
            $records = array_merge($records, $this->extractForLocale($content, $locale));
        }

        return $records;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<int, array{source_slug: string, record_key: string, title: string|null, content: string, locale: string}>
     */
    protected function extractForLocale(array $content, string $locale): array
    {
        $records = [];
        $schoolInfo = $content['schoolInfo'] ?? [];

        $name = trim((string) ($schoolInfo['name'] ?? ''));
        if ($name !== '') {
            $records[] = $this->record('school_info', 'name', $name, "School name: {$name}", $locale);
        }

        $tagline = trim((string) ($schoolInfo['tagline'] ?? ''));
        if ($tagline !== '') {
            $records[] = $this->record('school_info', 'tagline', $tagline, $tagline, $locale);
        }

        foreach (['phone', 'email', 'address', 'hours'] as $field) {
            $value = $this->contactValue($schoolInfo, $field);
            if ($value !== null) {
                $records[] = $this->record('contact', $field, ucfirst($field), $value, $locale);
            }
        }

        $hero = $schoolInfo['hero'] ?? [];
        $headline = trim(implode(' ', array_filter([
            $hero['headlineLine1'] ?? '',
            $hero['headlineAccent'] ?? '',
            $hero['headlineLine2'] ?? '',
        ])));
        if ($headline !== '') {
            $sub = (string) ($hero['subheadline'] ?? '');
            $records[] = $this->record('hero', 'headline', $headline, trim("{$headline}\n{$sub}"), $locale);
        }

        foreach ($content['navLinks'] ?? [] as $index => $link) {
            $label = trim((string) ($link['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $records[] = $this->record('navigation', "nav.{$index}", $label, $label, $locale);
        }

        foreach ($content['faqs'] ?? [] as $index => $faq) {
            $question = trim((string) ($faq['q'] ?? $faq['question'] ?? ''));
            $answer = trim((string) ($faq['a'] ?? $faq['answer'] ?? ''));
            if ($question === '' || $answer === '') {
                continue;
            }
            $records[] = $this->record('faq', "faq.{$index}", $question, "{$question}\n{$answer}", $locale);
        }

        foreach ($content['newsItems'] ?? [] as $index => $post) {
            $title = trim((string) ($post['title'] ?? ''));
            $excerpt = trim((string) ($post['excerpt'] ?? $post['summary'] ?? ''));
            if ($title === '') {
                continue;
            }
            $records[] = $this->record('news', "news.{$index}", $title, trim("{$title}\n{$excerpt}"), $locale);
        }

        foreach ($content['events'] ?? [] as $index => $event) {
            $title = trim((string) ($event['title'] ?? ''));
            $date = trim((string) ($event['date'] ?? ''));
            $description = trim((string) ($event['description'] ?? $event['excerpt'] ?? ''));
            if ($title === '') {
                continue;
            }
            $records[] = $this->record('events', "event.{$index}", $title, trim("{$title}\n{$date}\n{$description}"), $locale);
        }

        foreach ($content['admissionSteps'] ?? [] as $index => $step) {
            $title = trim((string) ($step['title'] ?? ''));
            $text = trim((string) ($step['text'] ?? ''));
            if ($title === '' && $text === '') {
                continue;
            }
            $records[] = $this->record('admissions', "step.{$index}", $title ?: "Step {$index}", trim("{$title}\n{$text}"), $locale);
        }

        foreach ($content['stages'] ?? [] as $stageIndex => $stage) {
            $stageTitle = trim((string) ($stage['title'] ?? ''));
            foreach ($stage['admission'] ?? [] as $itemIndex => $item) {
                $item = trim((string) $item);
                if ($item === '') {
                    continue;
                }
                $records[] = $this->record(
                    'admissions',
                    "stage.{$stageIndex}.{$itemIndex}",
                    $stageTitle ?: 'Admission requirement',
                    $item,
                    $locale,
                );
            }
        }

        foreach ($content['admissionDocuments'] ?? [] as $index => $doc) {
            $title = trim((string) ($doc['title'] ?? $doc['name'] ?? ''));
            $text = trim((string) ($doc['text'] ?? $doc['description'] ?? ''));
            if ($title === '' && $text === '') {
                continue;
            }
            $records[] = $this->record('policies', "doc.{$index}", $title ?: 'Policy', trim("{$title}\n{$text}"), $locale);
        }

        foreach ($content['academicPrograms'] ?? [] as $index => $program) {
            $title = trim((string) ($program['title'] ?? $program['name'] ?? ''));
            $text = trim((string) ($program['description'] ?? $program['text'] ?? ''));
            if ($title === '') {
                continue;
            }
            $records[] = $this->record('academic_programs', "program.{$index}", $title, trim("{$title}\n{$text}"), $locale);
        }

        $admissionsContact = trim((string) ($content['visitFormConfig']['contactLabel'] ?? ''));
        if ($admissionsContact !== '') {
            $records[] = $this->record('admissions', 'contact_label', 'Admissions contact', $admissionsContact, $locale);
        }

        return $records;
    }

    /**
     * @return array{source_slug: string, record_key: string, title: string|null, content: string, locale: string}
     */
    protected function record(string $source, string $key, ?string $title, string $content, string $locale): array
    {
        return [
            'source_slug' => $source,
            'record_key' => $key,
            'title' => $title,
            'content' => trim($content),
            'locale' => $locale,
        ];
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

    /**
     * @return array<string, mixed>
     */
    public function rawContentFromDefaults(): array
    {
        return WebsiteDefaultsRepository::load();
    }

    /**
     * @return array<int, array{source_slug: string, record_key: string, title: string|null, content: string, locale: string}>
     */
    public function buildFromPublishedFaqs(): array
    {
        $records = [];

        $faqs = DovaFaq::query()
            ->with('category')
            ->where('status', DovaFaq::STATUS_PUBLISHED)
            ->get();

        foreach ($faqs as $faq) {
            foreach (['en', 'ar'] as $locale) {
                $question = $locale === 'ar'
                    ? ($faq->question_ar ?: $faq->question_en)
                    : $faq->question_en;
                $answer = $locale === 'ar'
                    ? ($faq->answer_ar ?: $faq->answer_en)
                    : $faq->answer_en;

                if (trim($question) === '' || trim($answer) === '') {
                    continue;
                }

                $records[] = $this->record(
                    'faq',
                    "dova.{$faq->id}",
                    $question,
                    trim("{$question}\n{$answer}"),
                    $locale,
                );
            }
        }

        return $records;
    }
}
