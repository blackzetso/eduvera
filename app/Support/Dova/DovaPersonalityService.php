<?php

namespace App\Support\Dova;

/**
 * Dova voice & tone — natural bilingual responses between context and actions.
 */
class DovaPersonalityService
{
    /**
     * @param  array<string, mixed>  $ctx
     * @return array{headline: string, prompt: string, status: string, body: string}
     */
    public function welcomeCard(array $ctx, string $locale): array
    {
        $isAr = $locale === 'ar';
        $tone = $this->toneForRole($ctx['role'] ?? 'guest');
        $firstName = $this->firstName($ctx);

        $headline = $firstName
            ? ($isAr ? "مرحباً {$firstName}، أنا Dova 👋" : "Hi {$firstName}, I'm Dova 👋")
            : ($isAr ? 'مرحباً، أنا Dova 👋' : "Hi, I'm Dova 👋");

        $body = match ($tone) {
            'reassuring' => $isAr
                ? 'أنا هنا لمساعدتك في متابعة ابنك — الحضور، الدرجات، الرسوم، والجدول. اسأليني عن أي شيء وسأرشدك خطوة بخطوة.'
                : "I'm here to help you follow your child's progress — attendance, grades, fees, and schedules. Ask me anything and I'll guide you step by step.",
            'encouraging' => $isAr
                ? 'يسعدني مساعدتك في حصصك، حضورك، وكل ما يخص يومك الدراسي. اسألني بثقة وسأوضح لك الطريق.'
                : "I'm happy to help with your classes, attendance, and school day. Ask with confidence — I'll show you the way.",
            'instructional' => $isAr
                ? 'يمكنني مساعدتك في الحصص، الحضور، والجدول الدراسي بسرعة ووضوح. أخبرني بما تحتاجه.'
                : 'I can help with lessons, attendance, and your timetable — quickly and clearly. Tell me what you need.',
            'operational' => $isAr
                ? 'أنا مساعدتك اليومية في المنصة — الحضور، الطلاب، النماذج، والتقارير. كيف يمكنني تسهيل عملك؟'
                : "I'm your day-to-day platform assistant — attendance, students, forms, and reports. How can I make your work easier?",
            'strategic' => $isAr
                ? 'أنا هنا لدعم إدارة المدرسة — الطلاب، القبول، الموقع، والتقارير. ما الذي تود إنجازه الآن؟'
                : "I'm here to support school management — students, admissions, website, and reports. What would you like to accomplish?",
            default => $isAr
                ? 'يمكنني مساعدتك في التنقل داخل المنصة، الإجابة عن أسئلتك، وإرشادك خلال أي إجراء. كيف يمكنني مساعدتك اليوم؟'
                : 'I can help you navigate the platform, answer questions, and guide you through any process. How can I help you today?',
        };

        return [
            'headline' => $headline,
            'prompt' => $isAr ? 'كيف يمكنني مساعدتك اليوم؟' : 'How can I help you today?',
            'status' => $isAr ? 'متصل الآن' : 'Online',
            'body' => $body,
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    public function greeting(array $ctx, string $locale): string
    {
        return $this->welcomeCard($ctx, $locale)['prompt'];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array<int, string>
     */
    public function sampleQuestions(array $ctx, string $locale): array
    {
        $isAr = $locale === 'ar';
        $portal = $ctx['portal'] ?? 'public';
        $page = $ctx['page_context'] ?? 'home';

        $pools = [
            'home' => [
                'public' => [
                    $isAr ? 'كيف أقدّم للقبول؟' : 'How do I apply?',
                    $isAr ? 'كيف أدفع الرسوم؟' : 'How do I pay fees?',
                    $isAr ? 'كيف أحجز زيارة للمدرسة؟' : 'How do I book a campus visit?',
                    $isAr ? 'كيف أتواصل مع القبول؟' : 'How do I contact admissions?',
                ],
            ],
            'attendance' => [
                'admin' => [
                    $isAr ? 'كيف أسجّل الحضور؟' : 'How do I mark attendance?',
                    $isAr ? 'أين تقارير الحضور؟' : 'Where are attendance reports?',
                ],
                'teacher' => [
                    $isAr ? 'كيف أسجّل حضور الحصة؟' : 'How do I mark class attendance?',
                ],
                'guardian' => [
                    $isAr ? 'كيف أعرض حضور ابني؟' : "How do I view my child's attendance?",
                ],
            ],
            'students' => [
                'admin' => [
                    $isAr ? 'كيف أسجّل طالباً جديداً؟' : 'How do I register a new student?',
                    $isAr ? 'أين قائمة الطلاب؟' : 'Where is the student list?',
                ],
            ],
            'wallet' => [
                'guardian' => [
                    $isAr ? 'كيف أدفع الرسوم؟' : 'How do I pay fees?',
                    $isAr ? 'أين محفظتي؟' : 'Where is my wallet?',
                ],
            ],
        ];

        if (isset($pools[$page][$portal])) {
            return array_slice($pools[$page][$portal], 0, 4);
        }

        return match ($portal) {
            'admin' => [
                $isAr ? 'كيف أسجّل طالباً؟' : 'How do I register a student?',
                $isAr ? 'كيف أعرض الحضور؟' : 'How do I view attendance?',
                $isAr ? 'كيف أدير النماذج؟' : 'How do I manage forms?',
            ],
            'guardian' => [
                $isAr ? 'كيف أعرض الحضور؟' : 'How do I view attendance?',
                $isAr ? 'كيف أدفع الرسوم؟' : 'How do I pay fees?',
            ],
            'teacher' => [
                $isAr ? 'كيف أسجّل حضور الحصة؟' : 'How do I mark class attendance?',
                $isAr ? 'أين جدولي؟' : 'Where is my timetable?',
            ],
            default => [
                $isAr ? 'كيف أقدّم للقبول؟' : 'How do I apply?',
                $isAr ? 'كيف أدفع الرسوم؟' : 'How do I pay fees?',
                $isAr ? 'كيف أعرض الحضور؟' : 'How do I view attendance?',
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<int, array<string, mixed>>  $actions
     * @return array{
     *   introduction: string,
     *   explanation: string,
     *   footer: string,
     *   text: string,
     *   expression: string,
     *   workflow: array<int, string>|null
     * }
     */
    public function buildResponse(string $message, array $actions, array $ctx, string $locale): array
    {
        $isAr = $locale === 'ar';
        $topic = $this->detectTopic($message, $actions, $ctx);
        $tone = $this->toneForRole($ctx['role'] ?? 'guest');
        $expression = $this->resolveExpression($actions, $ctx, $topic);

        if ($actions === []) {
            return $this->recoveryResponse($isAr, $tone);
        }

        $copy = $this->topicCopy($topic, $isAr, $tone, $ctx);
        $introduction = $copy['introduction'];
        $explanation = $copy['explanation'];
        $footer = $isAr
            ? 'يمكنك استخدام الأزرار أدناه للانتقال مباشرة إلى الصفحات المناسبة.'
            : 'You can use the buttons below to go directly to the relevant pages.';

        $workflow = $this->buildWorkflow($actions, $topic, $isAr);

        return [
            'introduction' => $introduction,
            'explanation' => $explanation,
            'footer' => $footer,
            'text' => implode("\n\n", array_filter([$introduction, $explanation, $footer])),
            'expression' => $expression,
            'workflow' => $workflow,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $actions
     */
    protected function detectTopic(string $message, array $actions, array $ctx): string
    {
        $haystack = mb_strtolower($message.' '.($actions[0]['id'] ?? '').' '.$ctx['page_context']);

        $topics = [
            'admissions' => ['admission', 'apply', 'enroll', 'registration', 'قبول', 'تقديم', 'تسجيل', 'open_admissions', 'book_visit', 'admin_open_admissions'],
            'attendance' => ['attendance', 'absence', 'present', 'حضور', 'غياب', 'mark_attendance', 'attendance_'],
            'fees' => ['fee', 'fees', 'tuition', 'payment', 'wallet', 'رسوم', 'دفع', 'مصروفات', 'guardian_wallet', 'view_fee'],
            'students' => ['student', 'students', 'طالب', 'طلاب', 'create_student', 'students_list'],
            'forms' => ['form', 'application', 'نموذج', 'استمارة', 'create_form', 'forms_list'],
            'website' => ['website', 'cms', 'landing', 'موقع', 'website_cms'],
            'timetable' => ['timetable', 'schedule', 'جدول', 'timetable', 'teacher_timetable'],
        ];

        foreach ($topics as $topic => $needles) {
            foreach ($needles as $needle) {
                if (mb_stripos($haystack, mb_strtolower($needle)) !== false) {
                    return $topic;
                }
            }
        }

        return 'general';
    }

    protected function toneForRole(string $role): string
    {
        return match ($role) {
            'guardian' => 'reassuring',
            'student' => 'encouraging',
            'teacher' => 'instructional',
            'control_staff', 'social_worker', 'nurse', 'card_reader', 'department_head' => 'operational',
            'admin' => 'strategic',
            default => 'friendly',
        };
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    public function toneProfile(array $ctx): string
    {
        return $this->toneForRole($ctx['role'] ?? 'guest');
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    protected function resolveExpression(array $actions, array $ctx, string $topic): string
    {
        if ($actions === []) {
            return 'help';
        }

        $primary = $actions[0]['expression'] ?? 'explaining';

        if ($primary === 'success') {
            return 'celebrating';
        }

        if ($primary === 'teaching' || ($ctx['role'] ?? '') === 'teacher') {
            return 'teaching';
        }

        if ($primary === 'helping') {
            return 'help';
        }

        return 'explaining';
    }

    /**
     * @return array{introduction: string, explanation: string}
     */
    protected function topicCopy(string $topic, bool $isAr, string $tone, array $ctx): array
    {
        $intros = [
            'friendly' => $isAr ? 'يسعدني مساعدتك.' : "I'd be happy to help.",
            'reassuring' => $isAr ? 'لا تقلق، أنا هنا لمساعدتك.' : "Don't worry — I'm here to help.",
            'encouraging' => $isAr ? 'رائع أنك تسأل — دعني أوضح لك.' : 'Great question — let me walk you through it.',
            'instructional' => $isAr ? 'بالتأكيد، إليك ما تحتاج معرفته.' : "Of course — here's what you need to know.",
            'operational' => $isAr ? 'حسناً، إليك الطريقة الأسرع.' : "Got it — here's the quickest way.",
            'strategic' => $isAr ? 'ممتاز، إليك أفضل مسار للإنجاز.' : "Good — here's the best path forward.",
        ];

        $intro = $intros[$tone] ?? $intros['friendly'];

        $explanations = [
            'admissions' => $isAr
                ? "للتقديم، ابدأ بمراجعة شروط القبول والمراحل الدراسية المتاحة. بعد ذلك عبّئ نموذج التسجيل الإلكتروني وارفع المستندات المطلوبة. إذا رغبت، يمكنك أيضاً حجز زيارة للمدرسة قبل التقديم."
                : "To apply, start by reviewing the admissions requirements and available grade levels. Then complete the online application form and upload the required documents. You can also book a campus visit before applying if you'd like.",
            'attendance' => $isAr
                ? 'لمتابعة الحضور، افتح لوحة الحضور المناسبة لدورك — تسجيل الحضور، عرض السجلات، أو تقارير الغياب. سأوجهك مباشرة إلى الصفحة الصحيحة.'
                : 'To work with attendance, open the right attendance view for your role — marking records, viewing logs, or absence reports. I\'ll point you to the correct page.',
            'fees' => $isAr
                ? 'للرسوم والدفع، راجع هيكل المصروفات أولاً ثم انتقل إلى المحفظة أو صفحة السداد. ستجد هناك خيارات الدفع والتحويل المتاحة لك.'
                : 'For fees and payments, review the fee structure first, then go to your wallet or payment page. You\'ll find the payment and transfer options available to you.',
            'students' => $isAr
                ? 'لإدارة الطلاب، يمكنك إضافة طالب جديد من نموذج التسجيل أو فتح قائمة الطلاب للبحث والتعديل. أخبرني إن كنت تحتاج خطوات التسجيل بالتفصيل.'
                : 'To manage students, add a new student from the registration form or open the student list to search and update records. Let me know if you need the full registration steps.',
            'forms' => $isAr
                ? 'للنماذج، أنشئ نموذج تقديم جديد أو افتح قائمة النماذج لمتابعة التقديمات. يمكنك تخصيص الحقول حسب متطلبات القبول.'
                : 'For forms, create a new application form or open the forms list to track submissions. You can customize fields to match your admissions requirements.',
            'website' => $isAr
                ? 'لإدارة الموقع، افتح لوحة إدارة المحتوى لتحديث صفحات القبول، الأخبار، والعناصر الظاهرة للزوار.'
                : 'To manage the website, open the content dashboard to update admissions pages, news, and public-facing content.',
            'timetable' => $isAr
                ? 'للجدول الدراسي، افتح صفحة الجدول لعرض الحصص أو تعديل الإطار الزمني حسب صلاحياتك.'
                : 'For the timetable, open the schedule page to view periods or adjust the framework based on your permissions.',
            'general' => $isAr
                ? 'بناءً على سؤالك، جهّزت لك أقرب الإجراءات التي تحتاجها. اختر ما يناسبك وسأبقى معك إن احتجت خطوة تالية.'
                : 'Based on your question, I\'ve prepared the closest actions you need. Pick what fits and I\'ll stay with you for the next step.',
        ];

        return [
            'introduction' => $intro,
            'explanation' => $explanations[$topic] ?? $explanations['general'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $actions
     * @return array<int, string>|null
     */
    protected function buildWorkflow(array $actions, string $topic, bool $isAr): ?array
    {
        if (count($actions) < 2) {
            return null;
        }

        $guides = [
            'admissions' => $isAr
                ? ['مسار التقديم المقترح:', 'راجع شروط القبول', 'عبّئ نموذج التقديم', 'ارفع المستندات', 'أرسل الطلب']
                : ['Suggested application path:', 'Review requirements', 'Complete the form', 'Upload documents', 'Submit'],
            'attendance' => $isAr
                ? ['خطوات الحضور:', 'افتح لوحة الحضور', 'سجّل أو راجع السجلات', 'راجع التقارير عند الحاجة']
                : ['Attendance steps:', 'Open the attendance dashboard', 'Mark or review records', 'Check reports if needed'],
        ];

        if (isset($guides[$topic])) {
            return $guides[$topic];
        }

        $labels = array_map(fn ($a) => $a['label'], $actions);

        return $isAr
            ? array_merge(['يمكنك اتباع هذا الترتيب:'], $labels)
            : array_merge(['You can follow this order:'], $labels);
    }

    /**
     * @return array{introduction: string, explanation: string, footer: string, text: string, expression: string, workflow: null}
     */
    protected function recoveryResponse(bool $isAr, string $tone): array
    {
        $introduction = match ($tone) {
            'reassuring' => $isAr ? 'لم أجد إجراءً محدداً بعد، لكن يمكنني مساعدتك.' : "I couldn't match a specific action yet, but I can still help.",
            default => $isAr ? 'لم أتعرف على الطلب بدقة، لكن لا بأس.' : "I didn't quite match that request, but that's okay.",
        };

        $explanation = $isAr
            ? 'جرّب إعادة صياغة سؤالك، أو اختر أحد الاقتراحات أدناه. يمكنني أيضاً إرشادك للقبول، الحضور، الرسوم، أو التواصل مع الفريق المناسب.'
            : 'Try rephrasing your question, or pick one of the suggestions below. I can also guide you to admissions, attendance, fees, or the right team.';

        $footer = $isAr
            ? 'أنا هنا لمساعدتك — فقط أخبرني بما تبحث عنه.'
            : "I'm here to help — just tell me what you're looking for.";

        return [
            'introduction' => $introduction,
            'explanation' => $explanation,
            'footer' => $footer,
            'text' => implode("\n\n", [$introduction, $explanation, $footer]),
            'expression' => 'help',
            'workflow' => null,
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
}
