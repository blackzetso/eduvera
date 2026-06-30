<?php

namespace App\Support\Dova;

/**
 * Conversational intent layer — runs before the action/workflow engine.
 */
class DovaConversationIntentService
{
    /**
     * Priority: greeting → small_talk → thank_you → goodbye → help → workflow
     */
    public function detect(string $message): string
    {
        $normalized = $this->normalize($message);

        if ($normalized === '') {
            return 'workflow';
        }

        if ($this->isGreeting($normalized)) {
            return 'greeting';
        }

        if ($this->isSmallTalk($normalized)) {
            return 'small_talk';
        }

        if ($this->isThankYou($normalized)) {
            return 'thank_you';
        }

        if ($this->isGoodbye($normalized)) {
            return 'goodbye';
        }

        if ($this->isHelpRequest($normalized)) {
            return 'help';
        }

        return 'workflow';
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array{
     *   intent: string,
     *   introduction: string,
     *   explanation: string,
     *   footer: string,
     *   text: string,
     *   expression: string,
     *   actions: array,
     *   workflow: null
     * }
     */
    public function respond(string $intent, string $message, array $ctx, string $locale): array
    {
        $isAr = $locale === 'ar' || $this->messageIsArabic($message);
        $firstName = $this->firstName($ctx);

        $copy = match ($intent) {
            'greeting' => $this->greetingCopy($message, $isAr, $firstName),
            'small_talk' => $this->smallTalkCopy($isAr, $firstName),
            'thank_you' => $this->thankYouCopy($isAr),
            'goodbye' => $this->goodbyeCopy($isAr),
            'help' => $this->helpCopy($isAr, $ctx),
            default => [
                'introduction' => '',
                'explanation' => '',
                'footer' => '',
                'expression' => 'welcome',
            ],
        };

        $text = implode("\n\n", array_filter([
            $copy['introduction'],
            $copy['explanation'],
            $copy['footer'] ?? '',
        ]));

        return [
            'intent' => $intent,
            'introduction' => $copy['introduction'],
            'explanation' => $copy['explanation'],
            'footer' => $copy['footer'] ?? '',
            'text' => $text,
            'expression' => $copy['expression'],
            'actions' => [],
            'workflow' => null,
        ];
    }

    protected function normalize(string $message): string
    {
        $message = trim($message);
        $message = preg_replace('/[؟?!.،,:;]+/u', '', $message);
        $message = preg_replace('/\s+/u', ' ', $message);

        return mb_strtolower($message);
    }

    protected function isGreeting(string $normalized): bool
    {
        $patterns = [
            'السلام عليكم',
            'السلام عليكم ورحمة الله',
            'السلام عليكم ورحمة الله وبركاته',
            'سلام عليكم',
            'سلام',
            'مرحبا',
            'مرحباً',
            'أهلا',
            'أهلاً',
            'اهلا',
            'اهلاً',
            'صباح الخير',
            'مساء الخير',
            'hi',
            'hello',
            'hey',
            'good morning',
            'good evening',
            'good afternoon',
            'assalamu alaikum',
            'salam alaikum',
        ];

        foreach ($patterns as $pattern) {
            if ($normalized === $pattern || str_starts_with($normalized, $pattern.' ')) {
                return true;
            }
        }

        return false;
    }

    protected function isSmallTalk(string $normalized): bool
    {
        $patterns = [
            'كيف حالك',
            'كيفك',
            'كيف حالكم',
            'اخبارك',
            'أخبارك',
            'شلونك',
            'how are you',
            "how's it going",
            'how are things',
            'what\'s up',
            'whats up',
        ];

        foreach ($patterns as $pattern) {
            if ($normalized === $pattern || str_contains($normalized, $pattern)) {
                return true;
            }
        }

        return false;
    }

    protected function isThankYou(string $normalized): bool
    {
        $patterns = [
            'شكرا',
            'شكراً',
            'شكرا لك',
            'شكراً لك',
            'مشكور',
            'مشكورة',
            'تسلم',
            'تسلمي',
            'thanks',
            'thank you',
            'thank u',
            'thx',
            'much appreciated',
        ];

        foreach ($patterns as $pattern) {
            if ($normalized === $pattern || str_starts_with($normalized, $pattern)) {
                return true;
            }
        }

        return false;
    }

    protected function isGoodbye(string $normalized): bool
    {
        $patterns = [
            'مع السلامة',
            'مع السلامه',
            'الى اللقاء',
            'إلى اللقاء',
            'باي',
            'goodbye',
            'bye',
            'bye bye',
            'see you',
            'see ya',
            'take care',
        ];

        foreach ($patterns as $pattern) {
            if ($normalized === $pattern || str_starts_with($normalized, $pattern)) {
                return true;
            }
        }

        return false;
    }

    protected function isHelpRequest(string $normalized): bool
    {
        if ($this->looksLikeWorkflowQuestion($normalized)) {
            return false;
        }

        $patterns = [
            'help me',
            'i need help',
            'can you help',
            'مساعدة',
            'ساعدني',
            'احتاج مساعدة',
            'أحتاج مساعدة',
            'ممكن تساعدني',
        ];

        foreach ($patterns as $pattern) {
            if ($normalized === $pattern || str_contains($normalized, $pattern)) {
                return true;
            }
        }

        return false;
    }

    protected function looksLikeWorkflowQuestion(string $normalized): bool
    {
        return (bool) preg_match(
            '/how do i|how can i|where (is|are)|where do i|كيف (أ|ا)|أين |اين |وين |what is the|what are the/u',
            $normalized,
        );
    }

    /**
     * @return array{introduction: string, explanation: string, footer: string, expression: string}
     */
    protected function greetingCopy(string $message, bool $isAr, ?string $firstName): array
    {
        $normalized = $this->normalize($message);

        if ($isAr && str_contains($normalized, 'السلام عليكم')) {
            return [
                'introduction' => 'وعليكم السلام ورحمة الله وبركاته 🌷',
                'explanation' => $firstName
                    ? "أهلاً {$firstName}، يسعدني وجودك. كيف يمكنني مساعدتك اليوم؟"
                    : 'أهلاً بك، يسعدني مساعدتك. كيف يمكنني مساعدتك اليوم؟',
                'footer' => '',
                'expression' => 'welcome',
            ];
        }

        if (! $isAr && str_contains($normalized, 'good morning')) {
            return [
                'introduction' => 'Good morning! ☀️',
                'explanation' => $firstName
                    ? "Hi {$firstName}, great to see you. How can I help you today?"
                    : 'Hi there — lovely to meet you. How can I help you today?',
                'footer' => '',
                'expression' => 'welcome',
            ];
        }

        if (! $isAr && str_contains($normalized, 'good evening')) {
            return [
                'introduction' => 'Good evening! 🌙',
                'explanation' => $firstName
                    ? "Hi {$firstName}, how can I help you this evening?"
                    : 'Hi there — how can I help you this evening?',
                'footer' => '',
                'expression' => 'welcome',
            ];
        }

        if ($isAr && str_contains($normalized, 'صباح الخير')) {
            return [
                'introduction' => 'صباح النور ☀️',
                'explanation' => 'أهلاً بك، يسعدني مساعدتك. كيف يمكنني خدمتك اليوم؟',
                'footer' => '',
                'expression' => 'welcome',
            ];
        }

        if ($isAr && str_contains($normalized, 'مساء الخير')) {
            return [
                'introduction' => 'مساء النور 🌙',
                'explanation' => 'أهلاً بك، كيف يمكنني مساعدتك هذا المساء؟',
                'footer' => '',
                'expression' => 'welcome',
            ];
        }

        return [
            'introduction' => $isAr ? 'مرحباً بك 👋' : 'Hello! 👋',
            'explanation' => $firstName
                ? ($isAr
                    ? "أهلاً {$firstName}، يسعدني مساعدتك. كيف يمكنني خدمتك اليوم؟"
                    : "Hi {$firstName}, I'm Dova — happy to help. What can I do for you today?")
                : ($isAr
                    ? 'أهلاً بك، يسعدني مساعدتك. كيف يمكنني مساعدتك اليوم؟'
                    : "Hi, I'm Dova — happy to help. How can I assist you today?"),
            'footer' => '',
            'expression' => 'welcome',
        ];
    }

    /**
     * @return array{introduction: string, explanation: string, footer: string, expression: string}
     */
    protected function smallTalkCopy(bool $isAr, ?string $firstName): array
    {
        return [
            'introduction' => $isAr ? 'بخير، الحمد لله 😊' : "I'm doing well, thank you!",
            'explanation' => $isAr
                ? ($firstName
                    ? "سعيدة بتواصلك {$firstName}. أنا هنا لمساعدتك في أي شيء يخص المنصة."
                    : 'سعيدة بتواصلك. أنا هنا لمساعدتك في أي شيء يخص المنصة.')
                : ($firstName
                    ? "Lovely to hear from you, {$firstName}. I'm here whenever you need guidance on the platform."
                    : "Lovely to hear from you. I'm here whenever you need guidance on the platform."),
            'footer' => $isAr ? 'كيف يمكنني مساعدتك اليوم؟' : 'How can I help you today?',
            'expression' => 'welcome',
        ];
    }

    /**
     * @return array{introduction: string, explanation: string, footer: string, expression: string}
     */
    protected function thankYouCopy(bool $isAr): array
    {
        return [
            'introduction' => $isAr ? 'العفو، هذا واجبي 😊' : "You're very welcome!",
            'explanation' => $isAr
                ? 'يسعدني دائماً أن أكون بجانبك. إذا احتجت أي شيء آخر، أنا هنا.'
                : "I'm always happy to help. If you need anything else, I'm right here.",
            'footer' => '',
            'expression' => 'celebrating',
        ];
    }

    /**
     * @return array{introduction: string, explanation: string, footer: string, expression: string}
     */
    protected function goodbyeCopy(bool $isAr): array
    {
        return [
            'introduction' => $isAr ? 'مع السلامة 🌷' : 'Goodbye! 👋',
            'explanation' => $isAr
                ? 'أتمنى لك يوماً موفقاً. عد في أي وقت تحتاج مساعدة.'
                : 'Wishing you a wonderful day. Come back anytime you need help.',
            'footer' => '',
            'expression' => 'welcome',
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array{introduction: string, explanation: string, footer: string, expression: string}
     */
    protected function helpCopy(bool $isAr, array $ctx): array
    {
        $portal = $ctx['portal'] ?? 'public';

        $hint = match ($portal) {
            'guardian' => $isAr
                ? 'يمكنني مساعدتك في الحضور، الدرجات، الرسوم، والجدول.'
                : "I can help with attendance, grades, fees, and schedules.",
            'teacher' => $isAr
                ? 'يمكنني مساعدتك في الحصص، الحضور، والجدول الدراسي.'
                : 'I can help with classes, attendance, and your timetable.',
            'admin' => $isAr
                ? 'يمكنني مساعدتك في الطلاب، الحضور، النماذج، والتقارير.'
                : 'I can help with students, attendance, forms, and reports.',
            default => $isAr
                ? 'يمكنني مساعدتك في القبول، الرسوم، الزيارات، والتواصل.'
                : 'I can help with admissions, fees, campus visits, and contact.',
        };

        return [
            'introduction' => $isAr ? 'بالتأكيد، أنا هنا لمساعدتك.' : "Of course — I'm here to help.",
            'explanation' => $hint.' '.($isAr
                ? 'أخبرني بما تبحث عنه وسأرشدك مباشرة.'
                : 'Tell me what you\'re looking for and I\'ll guide you right away.'),
            'footer' => '',
            'expression' => 'help',
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    protected function firstName(array $ctx): ?string
    {
        $name = $ctx['user']['name'] ?? null;

        return $name ? explode(' ', trim($name))[0] : null;
    }

    protected function messageIsArabic(string $message): bool
    {
        return (bool) preg_match('/\p{Arabic}/u', $message);
    }
}
