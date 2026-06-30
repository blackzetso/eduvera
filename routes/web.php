<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\web\WebController;
use App\Http\Controllers\admin\FileController;
use App\Http\Controllers\admin\FormController;
use App\Http\Controllers\admin\FormSubmissionController;
use App\Http\Controllers\admin\LessonController;
use App\Http\Controllers\admin\LectureController;
use App\Http\Controllers\admin\SettingController;
use App\Http\Controllers\admin\CategoryController;
use App\Http\Controllers\admin\LanguageController;
use App\Http\Controllers\admin\DashboardController;
use App\Http\Controllers\admin\WalletController;
use App\Http\Controllers\admin\TimetableController;
use App\Http\Controllers\admin\DepartmentPlanController;
use App\Http\Controllers\admin\SubjectController;
use App\Http\Controllers\admin\TeacherController;
use App\Http\Controllers\admin\StudentController;
use App\Http\Controllers\admin\AdmissionApplicationController;
use App\Http\Controllers\admin\AdmissionDocumentSettingsController;
use App\Http\Controllers\admin\AttendanceController;
use App\Http\Controllers\admin\TeacherAbsenceDemoController;
use App\Http\Controllers\teacher\AttendanceController as TeacherAttendanceController;
use App\Http\Controllers\web\GuardianPortalController;
use App\Http\Controllers\teacher\TimetableController as TeacherTimetableController;
use App\Http\Controllers\teacher\DashboardController as TeacherDashboardController;
use App\Http\Controllers\teacher\LiveStreamController as TeacherLiveStreamController;
use App\Http\Controllers\web\FormController as WebFormController;
use App\Http\Controllers\admin\LiveStreamController;
use App\Http\Controllers\admin\Website\WebsiteDashboardController;
use App\Http\Controllers\admin\Website\WebsiteSettingsController;
use App\Http\Controllers\admin\Website\WebsiteStageController;
use App\Http\Controllers\admin\Website\WebsiteFacilityController;
use App\Http\Controllers\admin\Website\WebsiteEventController;
use App\Http\Controllers\admin\Website\WebsitePostController;
use App\Http\Controllers\admin\Website\WebsiteTestimonialController;
use App\Http\Controllers\admin\Website\WebsiteSuccessStoryController;
use App\Http\Controllers\admin\Website\WebsiteCareerController;
use App\Http\Controllers\admin\Website\WebsiteMediaController;
use App\Http\Controllers\admin\Website\WebsiteLandingBuilderController;
use App\Http\Controllers\admin\Website\WebsiteNavLinkController;
use App\Http\Controllers\admin\Website\WebsiteAnnouncementController;
use App\Http\Controllers\admin\Website\WebsiteChromeController;
use App\Http\Controllers\admin\Website\WebsiteUiLabelsController;
use App\Http\Controllers\admin\Website\WebsiteContentBlockController;
use App\Http\Controllers\admin\Website\WebsiteGalleryController;
use App\Http\Controllers\admin\Website\WebsiteCtaController;
use App\Http\Controllers\admin\DovaKnowledge\DovaKnowledgeController;
use App\Http\Controllers\admin\DovaKnowledge\DovaUnansweredController;
use App\Http\Controllers\admin\DovaKnowledge\DovaFaqGovernanceController;
use App\Http\Controllers\admin\DovaKnowledge\DovaFaqController;
use App\Http\Controllers\admin\DovaKnowledge\DovaKnowledgeGapController;
use App\Http\Controllers\admin\DovaKnowledge\DovaAiController;
use App\Http\Controllers\admin\LiveStreamQuizController;
use App\Http\Controllers\admin\LiveStreamExamController;
use App\Http\Controllers\StreamWatchController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// تشخيص الشاشة البيضاء: افتح /build-check على السيرفر لمعرفة إن كان الـ build موجود
Route::get('/build-check', function () {
    $manifestPath = public_path('build/manifest.json');
    $manifestExists = file_exists($manifestPath);
    $manifestAppFile = null;
    $manifestAppExists = false;
    $viteHtml = '';

    if ($manifestExists) {
        $manifest = json_decode(file_get_contents($manifestPath), true) ?: [];
        $manifestAppFile = $manifest['resources/js/app.js']['file'] ?? null;
        if ($manifestAppFile) {
            $manifestAppExists = file_exists(public_path('build/' . $manifestAppFile));
        }
    }

    try {
        $viteHtml = \Illuminate\Support\Facades\Vite::withEntryPoints(['resources/js/app.js'])->toHtml();
    } catch (\Throwable $e) {
        $viteHtml = 'خطأ: ' . $e->getMessage();
    }

    preg_match('/build\/assets\/app-[A-Za-z0-9_-]+\.js/', $viteHtml, $viteScriptMatch);
    $viteAppScript = $viteScriptMatch[0] ?? 'غير موجود';
    $cachedViewsCount = count(glob(storage_path('framework/views/*.php')) ?: []);
    $hotPath = public_path('hot');
    $hotExists = file_exists($hotPath);
    $hotActive = app(\App\Support\ViteHotFileGuard::class)->hotFileActive();

    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Build Check</title></head><body style="font-family:monospace; padding:20px; background:#f5f5f5; max-width:900px;">';
    $html .= '<h1>تشخيص الـ Build على السيرفر</h1>';
    $html .= '<ul>';
    $html .= '<li><strong>Laravel يعمل:</strong> نعم</li>';
    $html .= '<li><strong>APP_URL:</strong> ' . e(config('app.url')) . '</li>';
    $html .= '<li><strong>مسار الـ manifest:</strong> ' . e($manifestPath) . '</li>';
    $html .= '<li><strong>ملف public/hot موجود:</strong> ' . ($hotExists ? 'نعم' : 'لا') . '</li>';
    $html .= '<li><strong>Vite dev server نشط (5173):</strong> ' . ($hotActive ? 'نعم — وضع التطوير' : ($hotExists ? '<span style="color:red">لا (hot قديم — يُحذف تلقائياً)</span>' : 'لا — يستخدم public/build')) . '</li>';
    $html .= '<li><strong>ملف manifest.json موجود:</strong> ' . ($manifestExists ? 'نعم' : '<span style="color:red">لا — شغّل: npm run build</span>') . '</li>';
    $html .= '<li><strong>ملف app.js في manifest:</strong> ' . e($manifestAppFile ?? '—') . '</li>';
    $html .= '<li><strong>ملف app.js موجود على القرص:</strong> ' . ($manifestAppExists ? 'نعم' : '<span style="color:red">لا</span>') . '</li>';
    $html .= '<li><strong>ما يولّده @vite الآن:</strong> ' . e($viteAppScript) . '</li>';
    $html .= '<li><strong>Views مُجمّعة (cache):</strong> ' . $cachedViewsCount . ' ملف</li>';
    $html .= '<li><strong>APP_DEBUG:</strong> ' . (config('app.debug') ? 'true' : 'false') . '</li>';
    $html .= '</ul>';

    if ($manifestAppFile && $viteAppScript !== 'غير موجود' && ! str_contains($viteHtml, $manifestAppFile)) {
        $html .= '<p style="color:red; font-weight:bold;">⚠️ تعارض: manifest يشير لملف مختلف عما يُخدم في الصفحة. شغّل: php artisan view:clear && php artisan optimize:clear</p>';
    }

    if ($manifestAppFile && ! $manifestAppExists) {
        $html .= '<p style="color:red;">ارفع مجلد public/build كاملاً (manifest + assets) أو شغّل: npm run build</p>';
    }

    if (! $manifestExists && ! $hotActive) {
        $html .= '<p style="color:red; font-weight:bold;">⚠️ لا يوجد build ولا Vite dev. شغّل: <code>composer dev</code> أو <code>npm run build</code> ثم <code>composer serve</code></p>';
    }

    $html .= '<p style="color:#555; margin-top:1.5rem;"><strong>تشغيل موصى به:</strong> تطوير = <code>composer dev</code> · إنتاج محلي = <code>npm run build</code> ثم <code>composer serve</code></p>';
    $html .= '</body></html>';
    return response($html)->header('Content-Type', 'text/html; charset=utf-8');
})->name('build.check');

// Public Routes (للطلاب والزوار)
Route::get('/', [WebController::class, 'home'])->name('home');
Route::get('/school-talent/{type}/{slug}', [WebController::class, 'schoolTalentArticle'])
    ->whereIn('type', ['news', 'blog'])
    ->name('school-talent.article');
Route::redirect('/school-talent', '/', 301);
Route::get('/explore', [WebController::class, 'explore'])->name('explore');

// Public live stream join pages (no auth required)
Route::get('/join/{liveStream}', [LiveStreamController::class, 'guestJoin'])->name('live-streams.guest-join');
Route::get('/join/{liveStream}/status', [LiveStreamController::class, 'guestStatus'])->name('live-streams.guest-status');
Route::post('/join/{liveStream}/token', [LiveStreamController::class, 'guestToken'])->name('live-streams.guest-token');
Route::post('/join/{liveStream}/livekit-token', [LiveStreamController::class, 'guestLivekitToken'])->name('live-streams.livekit-token');

// Stream watch page (recorded sessions — requires auth)
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])
    ->group(function () {
        Route::get('/streams/{liveStream}/watch', [StreamWatchController::class, 'show'])->name('streams.watch');
        Route::get('/streams/{liveStream}/stream-recording', [StreamWatchController::class, 'stream'])->name('streams.stream-recording');
        Route::get('/streams/{liveStream}/download-recording', [StreamWatchController::class, 'download'])->name('streams.download-recording');
    });
// Public quiz routes (student polling & submitting)
Route::get('/join/{liveStream}/quiz/active', [LiveStreamQuizController::class, 'activeQuestion'])->name('live-streams.quiz.active');
Route::post('/join/{liveStream}/quiz/{quiz}/answer', [LiveStreamQuizController::class, 'submitAnswer'])->name('live-streams.quiz.submit');
// Public exam routes (student)
Route::get('/join/{liveStream}/exam/active', [LiveStreamExamController::class, 'activeExam'])->name('live-streams.exam.active');
Route::post('/join/{liveStream}/exam/{session}/submit', [LiveStreamExamController::class, 'submitExam'])->name('live-streams.exam.submit');
Route::get('/lessons', [WebController::class, 'lessons'])->name('lessons');
Route::get('/lessons/{id}', [WebController::class, 'showLesson'])->name('lesson.show');
Route::get('/teachers', [WebController::class, 'teachers'])->name('teachers');
Route::get('/blog', [WebController::class, 'blog'])->name('blog');
Route::get('form/{id}', [WebFormController::class, 'index'])->name('web.form');

// Dova Platform Copilot (action-oriented assistant API)
Route::prefix('dova')->name('dova.')->group(function () {
    Route::get('/copilot/context', [\App\Http\Controllers\Api\DovaCopilotController::class, 'context'])->name('copilot.context');
    Route::post('/copilot/suggest', [\App\Http\Controllers\Api\DovaCopilotController::class, 'suggest'])->name('copilot.suggest');
    Route::post('/copilot/feedback', [\App\Http\Controllers\Api\DovaCopilotController::class, 'feedback'])->name('copilot.feedback');
    Route::post('/copilot/voice/transcribe', [\App\Http\Controllers\Api\DovaCopilotController::class, 'transcribe'])->name('copilot.voice.transcribe');
    Route::post('/copilot/voice/recognition', [\App\Http\Controllers\Api\DovaCopilotController::class, 'logRecognition'])->name('copilot.voice.recognition');
});

// Language Switcher
Route::get('set-locale/{locale}', function (string $locale) {
    $lang = in_array($locale, ['ar', 'en'], true) ? $locale : config('app.locale', 'ar');
    session(['locale' => $lang]);
    app()->setLocale($lang);
    return back();
});

Route::post('/change-language', function (Request $request) {
    $lang = in_array($request->input('lang'), ['ar', 'en'], true)
        ? $request->input('lang')
        : config('app.locale', 'ar');
    session(['locale' => $lang]);
    app()->setLocale($lang);
    return back();
})->name('change.language');

// ============================================================================
// Fortify Authentication Routes
// ============================================================================
// These routes are manually registered here to ensure they work properly.
// Fortify's automatic route registration is disabled in FortifyServiceProvider.
// ============================================================================

$enableViews = config('fortify.views', true);
$limiter = config('fortify.limiters.login');
$twoFactorLimiter = config('fortify.limiters.two-factor');

// Authentication
Route::group(['middleware' => config('fortify.middleware', ['web'])], function () use ($enableViews, $limiter, $twoFactorLimiter) {
    // Login
    if ($enableViews) {
        Route::get('/login', [Laravel\Fortify\Http\Controllers\AuthenticatedSessionController::class, 'create'])
            ->middleware(['guest:'.config('fortify.guard')])
            ->name('login');
    }

    Route::post('/login', [Laravel\Fortify\Http\Controllers\AuthenticatedSessionController::class, 'store'])
        ->middleware(array_filter([
            'guest:'.config('fortify.guard'),
            $limiter ? 'throttle:'.$limiter : null,
        ]))
        ->name('login.store');

    Route::post('/logout', [Laravel\Fortify\Http\Controllers\AuthenticatedSessionController::class, 'destroy'])
        ->middleware([config('fortify.auth_middleware', 'auth').':'.config('fortify.guard')])
        ->name('logout');

    // Registration
    if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::registration())) {
        if ($enableViews) {
            Route::get('/register', [Laravel\Fortify\Http\Controllers\RegisteredUserController::class, 'create'])
                ->middleware(['guest:'.config('fortify.guard')])
                ->name('register');
        }

        Route::post('/register', [Laravel\Fortify\Http\Controllers\RegisteredUserController::class, 'store'])
            ->middleware(['guest:'.config('fortify.guard')])
            ->name('register.store');
    }

    // Password Reset
    if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::resetPasswords())) {
        if ($enableViews) {
            Route::get('/forgot-password', [Laravel\Fortify\Http\Controllers\PasswordResetLinkController::class, 'create'])
                ->middleware(['guest:'.config('fortify.guard')])
                ->name('password.request');

            Route::get('/reset-password/{token}', [Laravel\Fortify\Http\Controllers\NewPasswordController::class, 'create'])
                ->middleware(['guest:'.config('fortify.guard')])
                ->name('password.reset');
        }

        Route::post('/forgot-password', [Laravel\Fortify\Http\Controllers\PasswordResetLinkController::class, 'store'])
            ->middleware(['guest:'.config('fortify.guard')])
            ->name('password.email');

        Route::post('/reset-password', [Laravel\Fortify\Http\Controllers\NewPasswordController::class, 'store'])
            ->middleware(['guest:'.config('fortify.guard')])
            ->name('password.update');
    }

    // Email Verification
    if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::emailVerification())) {
        if ($enableViews) {
            Route::get('/email/verify', [Laravel\Fortify\Http\Controllers\EmailVerificationPromptController::class, '__invoke'])
                ->middleware([config('fortify.auth_middleware', 'auth').':'.config('fortify.guard')])
                ->name('verification.notice');
        }

        Route::get('/email/verify/{id}/{hash}', [Laravel\Fortify\Http\Controllers\VerifyEmailController::class, '__invoke'])
            ->middleware([config('fortify.auth_middleware', 'auth').':'.config('fortify.guard'), 'signed', 'throttle:6,1'])
            ->name('verification.verify');

        Route::post('/email/verification-notification', [Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController::class, 'store'])
            ->middleware([config('fortify.auth_middleware', 'auth').':'.config('fortify.guard'), 'throttle:6,1'])
            ->name('verification.send');
    }

    // Profile Information
    if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updateProfileInformation())) {
        Route::put('/user/profile-information', [Laravel\Fortify\Http\Controllers\ProfileInformationController::class, 'update'])
            ->middleware([config('fortify.auth_middleware', 'auth').':'.config('fortify.guard')])
            ->name('user-profile-information.update');
    }

    // Passwords
    if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords())) {
        Route::put('/user/password', [Laravel\Fortify\Http\Controllers\PasswordController::class, 'update'])
            ->middleware([config('fortify.auth_middleware', 'auth').':'.config('fortify.guard')])
            ->name('user-password.update');
    }

    // Password Confirmation
    if ($enableViews) {
        Route::get('/user/confirm-password', [Laravel\Fortify\Http\Controllers\ConfirmablePasswordController::class, 'show'])
            ->middleware([config('fortify.auth_middleware', 'auth').':'.config('fortify.guard')])
            ->name('password.confirm');
    }

    Route::post('/user/confirm-password', [Laravel\Fortify\Http\Controllers\ConfirmedPasswordStatusController::class, 'store'])
        ->middleware([config('fortify.auth_middleware', 'auth').':'.config('fortify.guard')])
        ->name('password.confirm.store');

    Route::get('/user/confirmed-password-status', [Laravel\Fortify\Http\Controllers\ConfirmedPasswordStatusController::class, 'show'])
        ->middleware([config('fortify.auth_middleware', 'auth').':'.config('fortify.guard')])
        ->name('password.confirmation');

    // Two Factor Authentication
    if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::twoFactorAuthentication())) {
        if ($enableViews) {
            Route::get('/two-factor-challenge', [Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController::class, 'create'])
                ->middleware(['guest:'.config('fortify.guard')])
                ->name('two-factor.login');
        }

        Route::post('/two-factor-challenge', [Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController::class, 'store'])
            ->middleware(array_filter([
                'guest:'.config('fortify.guard'),
                $twoFactorLimiter ? 'throttle:'.$twoFactorLimiter : null,
            ]))
            ->name('two-factor.login.store');

        $twoFactorMiddleware = [
            config('fortify.auth_middleware', 'auth').':'.config('fortify.guard'),
        ];

        if ($confirmPasswordMiddleware = config('fortify.confirmPasswordMiddleware', 'password.confirm')) {
            $twoFactorMiddleware[] = $confirmPasswordMiddleware;
        }

        Route::post('/user/two-factor-authentication', [Laravel\Fortify\Http\Controllers\TwoFactorAuthenticationController::class, 'store'])
            ->middleware($twoFactorMiddleware)
            ->name('two-factor.enable');

        Route::post('/user/confirmed-two-factor-authentication', [Laravel\Fortify\Http\Controllers\ConfirmedTwoFactorAuthenticationController::class, 'store'])
            ->middleware($twoFactorMiddleware)
            ->name('two-factor.confirm');

        Route::delete('/user/two-factor-authentication', [Laravel\Fortify\Http\Controllers\TwoFactorAuthenticationController::class, 'destroy'])
            ->middleware($twoFactorMiddleware)
            ->name('two-factor.disable');

        Route::get('/user/two-factor-qr-code', [Laravel\Fortify\Http\Controllers\TwoFactorQrCodeController::class, 'show'])
            ->middleware($twoFactorMiddleware)
            ->name('two-factor.qr-code');

        Route::get('/user/two-factor-secret-key', [Laravel\Fortify\Http\Controllers\TwoFactorSecretKeyController::class, 'show'])
            ->middleware($twoFactorMiddleware)
            ->name('two-factor.secret-key');

        Route::get('/user/two-factor-recovery-codes', [Laravel\Fortify\Http\Controllers\RecoveryCodeController::class, 'index'])
            ->middleware($twoFactorMiddleware)
            ->name('two-factor.recovery-codes');

        Route::post('/user/two-factor-recovery-codes', [Laravel\Fortify\Http\Controllers\RecoveryCodeController::class, 'store'])
            ->middleware($twoFactorMiddleware)
            ->name('two-factor.recovery-codes.store');
    }
});

// ============================================================================
// End Fortify Authentication Routes
// ============================================================================

// Student Routes
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->prefix('student')->as('student.')->group(function () {
    $getStudentAllowedCategoryIds = function () {
        $studentCategoryId = \Illuminate\Support\Facades\Auth::user()?->category_id;

        if (!$studentCategoryId) {
            return [];
        }

        $parentMap = \App\Models\Category::query()->pluck('parent_id', 'id');
        $allowed   = [];
        $current   = (int) $studentCategoryId;

        while ($current > 0) {
            $allowed[] = $current;
            $parentId = $parentMap->get($current);
            $current  = $parentId ? (int) $parentId : 0;
        }

        return array_values(array_unique($allowed));
    };

    $ensureStudentStreamAccess = function (\App\Models\LiveStream $liveStream) use ($getStudentAllowedCategoryIds) {
        $allowedCategoryIds = $getStudentAllowedCategoryIds();
        abort_if(!in_array((int) $liveStream->category_id, $allowedCategoryIds, true), 404);
    };

    Route::get('/dashboard', function () {
        return Inertia::render('Student/Theme1/Dashboard/Index');
    })->name('dashboard');

    Route::get('/live-streams', function () use ($getStudentAllowedCategoryIds) {
        $allowedCategoryIds = $getStudentAllowedCategoryIds();

        $streams = \App\Models\LiveStream::whereIn('category_id', $allowedCategoryIds)
            ->orderByRaw("CASE status WHEN 'live' THEN 0 WHEN 'scheduled' THEN 1 ELSE 2 END")
            ->orderBy('start_datetime', 'desc')
            ->get()
            ->map(fn ($s) => [
                'id'             => $s->id,
                'title'          => $s->title,
                'subject'        => $s->subject,
                'teacher_name'   => $s->teacher_name,
                'status'         => $s->status,
                'start_datetime' => $s->start_datetime?->format('Y-m-d H:i'),
                'join_url'       => route('live-streams.guest-join', $s->id),
                'thumbnail_url'  => $s->thumbnail_path ? asset('storage/' . $s->thumbnail_path) : null,
            ]);

        return \Inertia\Inertia::render('Student/Theme1/LiveStreams', [
            'streams' => $streams,
        ]);
    })->name('live-streams.index');

    Route::get('/live-streams/{liveStream}', function (\App\Models\LiveStream $liveStream) use ($ensureStudentStreamAccess) {
        $ensureStudentStreamAccess($liveStream);

        return \Inertia\Inertia::render('Student/Theme1/LiveStreamDetail', [
            'stream' => [
                'id'              => $liveStream->id,
                'title'           => $liveStream->title,
                'description'     => $liveStream->description,
                'learning_points' => $liveStream->learning_points ?? [],
                'subject'         => $liveStream->subject,
                'teacher_name'    => $liveStream->teacher_name,
                'teacher_email'   => $liveStream->teacher_email,
                'status'          => $liveStream->status,
                'start_datetime'  => $liveStream->start_datetime?->format('Y-m-d H:i'),
                'end_datetime'    => $liveStream->end_datetime?->format('Y-m-d H:i'),
                'join_url'        => route('live-streams.guest-join', $liveStream->id),
                'thumbnail_url'   => $liveStream->thumbnail_path ? asset('storage/' . $liveStream->thumbnail_path) : null,
                'video_url'       => $liveStream->video_url,
                'has_recording'   => !empty($liveStream->video_url) || (!empty($liveStream->recording_path) && \Illuminate\Support\Facades\Storage::disk('local')->exists($liveStream->recording_path)),
                'watch_url'       => route('streams.watch', $liveStream->id),
            ],
        ]);
    })->name('live-streams.show');

    // Reviews API
    Route::get('/live-streams/{liveStream}/reviews', function (\App\Models\LiveStream $liveStream) use ($ensureStudentStreamAccess) {
        $ensureStudentStreamAccess($liveStream);

        $reviews = $liveStream->reviews()
            ->latest()
            ->get()
            ->map(fn ($r) => [
                'id'             => $r->id,
                'reviewer_name'  => $r->reviewer_name,
                'rating'         => $r->rating,
                'body'           => $r->body,
                'created_at'     => $r->created_at->diffForHumans(),
            ]);

        $avg   = $reviews->avg('rating') ?? 0;
        $count = $reviews->count();

        return response()->json([
            'reviews' => $reviews,
            'avg'     => round($avg, 1),
            'count'   => $count,
        ]);
    })->name('live-streams.reviews.index');

    Route::post('/live-streams/{liveStream}/reviews', function (\Illuminate\Http\Request $request, \App\Models\LiveStream $liveStream) use ($ensureStudentStreamAccess) {
        $ensureStudentStreamAccess($liveStream);

        $data = $request->validate([
            'reviewer_name'  => 'required|string|max:100',
            'reviewer_email' => 'nullable|email|max:150',
            'rating'         => 'required|integer|min:1|max:5',
            'body'           => 'required|string|max:2000',
        ]);

        $review = $liveStream->reviews()->create($data);

        return response()->json([
            'id'            => $review->id,
            'reviewer_name' => $review->reviewer_name,
            'rating'        => $review->rating,
            'body'          => $review->body,
            'created_at'    => $review->created_at->diffForHumans(),
        ], 201);
    })->name('live-streams.reviews.store');

    // Comments API
    Route::get('/live-streams/{liveStream}/comments', function (\App\Models\LiveStream $liveStream) use ($ensureStudentStreamAccess) {
        $ensureStudentStreamAccess($liveStream);

        $comments = $liveStream->comments()
            ->with(['replies'])
            ->get()
            ->map(fn ($c) => [
                'id'          => $c->id,
                'author_name' => $c->author_name,
                'body'        => $c->body,
                'created_at'  => $c->created_at->diffForHumans(),
                'replies'     => $c->replies->map(fn ($r) => [
                    'id'          => $r->id,
                    'author_name' => $r->author_name,
                    'body'        => $r->body,
                    'created_at'  => $r->created_at->diffForHumans(),
                ])->values(),
            ]);

        return response()->json(['comments' => $comments]);
    })->name('live-streams.comments.index');

    Route::post('/live-streams/{liveStream}/comments', function (\Illuminate\Http\Request $request, \App\Models\LiveStream $liveStream) use ($ensureStudentStreamAccess) {
        $ensureStudentStreamAccess($liveStream);

        $data = $request->validate([
            'author_name'  => 'required|string|max:100',
            'author_email' => 'nullable|email|max:150',
            'body'         => 'required|string|max:2000',
        ]);

        $comment = $liveStream->comments()->create($data);

        return response()->json([
            'id'          => $comment->id,
            'author_name' => $comment->author_name,
            'body'        => $comment->body,
            'created_at'  => $comment->created_at->diffForHumans(),
            'replies'     => [],
        ], 201);
    })->name('live-streams.comments.store');

    Route::post('/live-streams/{liveStream}/comments/{comment}/replies', function (\Illuminate\Http\Request $request, \App\Models\LiveStream $liveStream, \App\Models\LiveStreamComment $comment) use ($ensureStudentStreamAccess) {
        $ensureStudentStreamAccess($liveStream);
        abort_if($comment->live_stream_id !== $liveStream->id, 404);

        $data = $request->validate([
            'author_name'  => 'required|string|max:100',
            'author_email' => 'nullable|email|max:150',
            'body'         => 'required|string|max:2000',
        ]);

        $data['parent_id'] = $comment->id;
        $reply = $liveStream->comments()->create($data);

        return response()->json([
            'id'          => $reply->id,
            'author_name' => $reply->author_name,
            'body'        => $reply->body,
            'created_at'  => $reply->created_at->diffForHumans(),
        ], 201);
    })->name('live-streams.comments.reply');
});

// Admin Routes
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'admin',
])->prefix('admin')->as('admin.')->group(function () {

    // Dashboard
    Route::resource('dashboard', DashboardController::class);

    // Form Builder
    Route::resource('forms', FormController::class);
    Route::patch('/forms/{id}/status', [FormController::class, 'toggleStatus'])->name('forms.status');
    Route::get('/forms/search/{phrase}', [FormController::class, 'search'])->name('forms.search');
    Route::get('/forms/templates/list', [FormController::class, 'templates'])->name('forms.templates');
    Route::get('/forms/templates/{key}', [FormController::class, 'template'])->name('forms.template');
    Route::post('/forms/translate-bilingual', [FormController::class, 'translateBilingual'])->name('forms.translate-bilingual');
    Route::get('/forms/{form}/submissions', [FormSubmissionController::class, 'index'])->name('forms.submissions.index');

    // Lesson Categories
    Route::resource('categories', CategoryController::class);
    Route::delete('/categories-destroy-all', [CategoryController::class, 'destroyAll'])->name('categories.destroy-all');
    Route::patch('/categories/{id}/status', [CategoryController::class, 'toggleStatus'])->name('categories.status');
    Route::get('/categories/search/{phrase}', [CategoryController::class, 'search'])->name('categories.search');
    Route::get('/categories/{id}/duplicate-info', [CategoryController::class, 'duplicateInfo'])->name('categories.duplicate-info');
    Route::post('/categories/{id}/duplicate', [CategoryController::class, 'duplicate'])->name('categories.duplicate');

    // Subjects
    Route::resource('subjects', SubjectController::class);
    Route::get('/subjects/search/{phrase}', [SubjectController::class, 'search'])->name('subjects.search');

    // Lessons
    Route::resource('lessons', LessonController::class);
    Route::patch('/lessons/{id}/status', [LessonController::class, 'toggleStatus'])->name('lessons.status');
    Route::get('/lessons/search/{phrase}', [LessonController::class, 'search'])->name('lessons.search');

    // Lesson Message Templates
    Route::resource('lesson-message-templates', \App\Http\Controllers\admin\LessonMessageTemplateController::class)
        ->except(['create', 'edit', 'show']);
    Route::patch('/lesson-message-templates/{lessonMessageTemplate}/status', [\App\Http\Controllers\admin\LessonMessageTemplateController::class, 'toggleStatus'])
        ->name('lesson-message-templates.toggle-status');

    // Lectures
    Route::resource('lectures', LectureController::class);

    // Files (Videos)
    Route::resource('files', FileController::class);
    Route::post('files/upload-to-bunny', [FileController::class, 'uploadToBunny'])->name('files.uploadToBunny');
    Route::post('files/save-youtube-link', [FileController::class, 'saveYoutubeLink'])->name('files.saveYoutubeLink');
    Route::post('files/save-external-link', [FileController::class, 'saveExternalLink'])->name('files.saveExternalLink');

    // Wallet (محفظة التخزين)
    Route::prefix('wallet')->name('wallet.')->group(function () {
        Route::get('/', [WalletController::class, 'index'])->name('index');
        Route::post('/activate', [FileController::class, 'activateWallet'])->name('activate');
        Route::get('/recharge', [WalletController::class, 'showRecharge'])->name('recharge');
        Route::post('/recharge', [WalletController::class, 'recharge'])->name('recharge.process');
        Route::get('/payment/{rechargeRequest}', [WalletController::class, 'processPayment'])->name('payment');
        Route::get('/payment/{rechargeRequest}/show-code', [WalletController::class, 'showPaymentCode'])->name('payment.show-code');
        Route::post('/payment/{rechargeRequest}/check-status', [WalletController::class, 'checkPaymentStatus'])->name('payment.check-status');
        Route::post('/payment/{rechargeRequest}/cancel', [WalletController::class, 'cancelRecharge'])->name('payment.cancel');
        Route::get('/payment-callback', [WalletController::class, 'paymentCallback'])->name('payment.callback');
        Route::post('/payment-webhook', [WalletController::class, 'paymentWebhook'])->name('payment.webhook');
        Route::post('/sync-consumption', [WalletController::class, 'syncConsumption'])->name('syncConsumption');
        Route::get('/test-fawaterak', [WalletController::class, 'testFawaterak'])->name('testFawaterak');
    });

    // Settings
    Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
    Route::get('settings/teams', [SettingController::class, 'teamsSettings'])->name('settings.teams');
    Route::post('settings/teams', [SettingController::class, 'updateTeamsSettings'])->name('settings.teams.update');
    Route::get('settings/zoom', [SettingController::class, 'zoomSettings'])->name('settings.zoom');
    Route::post('settings/zoom', [SettingController::class, 'updateZoomSettings'])->name('settings.zoom.update');
    Route::get('settings/livekit', [SettingController::class, 'livekitSettings'])->name('settings.livekit');
    Route::post('settings/livekit', [SettingController::class, 'updateLivekitSettings'])->name('settings.livekit.update');
    Route::get('settings/google-meet', [SettingController::class, 'googleMeetSettings'])->name('settings.google-meet');
    Route::post('settings/google-meet', [SettingController::class, 'updateGoogleMeetSettings'])->name('settings.google-meet.update');
    Route::get('settings/hms', [SettingController::class, 'hmsSettings'])->name('settings.hms');
    Route::post('settings/hms', [SettingController::class, 'updateHmsSettings'])->name('settings.hms.update');
    Route::get('settings/coverage', [SettingController::class, 'coverageSettings'])->name('settings.coverage');
    Route::post('settings/coverage', [SettingController::class, 'updateCoverageSettings'])->name('settings.coverage.update');
    Route::get('live-streams/details', [SettingController::class, 'liveStreamDetails'])->name('live-streams.details');
    Route::post('live-streams/details', [SettingController::class, 'updateLiveStreamDetails'])->name('live-streams.details.update');
    Route::resource('language', LanguageController::class);

    // Live Streams
    Route::patch('/live-streams/{liveStream}/extra-session/approve', [LiveStreamController::class, 'approveExtraSession'])->name('live-streams.extra-session.approve');
    Route::patch('/live-streams/{liveStream}/extra-session/cancel', [LiveStreamController::class, 'cancelExtraSession'])->name('live-streams.extra-session.cancel');
    // Live extension (during stream)
    Route::post('/live-streams/{liveStream}/request-extension', [LiveStreamController::class, 'requestExtension'])->name('live-streams.request-extension');
    Route::patch('/live-streams/{liveStream}/extension/cancel', [LiveStreamController::class, 'cancelExtension'])->name('live-streams.extension.cancel');
    Route::patch('/live-streams/{liveStream}/extension/approve', [LiveStreamController::class, 'approveExtension'])->name('live-streams.extension.approve');
    Route::get('/live-streams/{liveStream}/remaining-seconds', [LiveStreamController::class, 'remainingSeconds'])->name('live-streams.remaining-seconds');
    // Recording
    Route::post('/live-streams/{liveStream}/upload-recording', [LiveStreamController::class, 'uploadRecording'])->name('live-streams.upload-recording');
    Route::post('/live-streams/{liveStream}/upload-wb-media', [LiveStreamController::class, 'uploadWbMedia'])->name('live-streams.upload-wb-media');
    Route::patch('/live-streams/{liveStream}/video-url', [LiveStreamController::class, 'submitVideoUrl'])->name('live-streams.video-url');
    Route::resource('live-streams', LiveStreamController::class);
    Route::post('/live-streams/{liveStream}/sync-attendance', [LiveStreamController::class, 'syncAttendance'])->name('live-streams.sync-attendance');
    Route::patch('/live-streams/{liveStream}/status', [LiveStreamController::class, 'updateStatus'])->name('live-streams.update-status');
    Route::get('/live-streams/{liveStream}/room', [LiveStreamController::class, 'room'])->name('live-streams.room');
    // Quiz routes (teacher)
    Route::get('/live-streams/{liveStream}/quiz', [LiveStreamQuizController::class, 'index'])->name('live-streams.quiz.index');
    Route::post('/live-streams/{liveStream}/quiz', [LiveStreamQuizController::class, 'store'])->name('live-streams.quiz.store');
    Route::patch('/live-streams/{liveStream}/quiz/set-time', [LiveStreamQuizController::class, 'setTime'])->name('live-streams.quiz.set-time');
    Route::put('/live-streams/{liveStream}/quiz/{quiz}', [LiveStreamQuizController::class, 'update'])->name('live-streams.quiz.update');
    Route::delete('/live-streams/{liveStream}/quiz/{quiz}', [LiveStreamQuizController::class, 'destroy'])->name('live-streams.quiz.destroy');
    Route::patch('/live-streams/{liveStream}/quiz/{quiz}/activate', [LiveStreamQuizController::class, 'activate'])->name('live-streams.quiz.activate');
    Route::patch('/live-streams/{liveStream}/quiz/{quiz}/close', [LiveStreamQuizController::class, 'close'])->name('live-streams.quiz.close');
    Route::get('/live-streams/{liveStream}/quiz/{quiz}/answers', [LiveStreamQuizController::class, 'answers'])->name('live-streams.quiz.answers');
    // Exam routes (teacher)
    Route::post('/live-streams/{liveStream}/exam/launch', [LiveStreamExamController::class, 'launch'])->name('live-streams.exam.launch');
    Route::patch('/live-streams/{liveStream}/exam/{session}/close', [LiveStreamExamController::class, 'close'])->name('live-streams.exam.close');
    Route::get('/live-streams/{liveStream}/exam/{session}/status', [LiveStreamExamController::class, 'status'])->name('live-streams.exam.status');
    Route::patch('/language/{id}/status', [LanguageController::class, 'toggleStatus'])->name('language.status');
    Route::get('/language/search/{phrase}', [LanguageController::class, 'search'])->name('language.search');

    // Timetable (Single table for the school)
    Route::get('teachers/bulk-data', [TeacherController::class, 'bulkData'])->name('teachers.bulk-data');
    Route::get('teachers/bulk-data/template', [TeacherController::class, 'bulkDataTemplate'])->name('teachers.bulk-data.template');
    Route::post('teachers/bulk-data/import', [TeacherController::class, 'bulkDataImport'])->name('teachers.bulk-data.import');
    Route::get('students/bulk-data', [StudentController::class, 'bulkData'])->name('students.bulk-data');
    Route::get('students/bulk-data/template', [StudentController::class, 'bulkDataTemplate'])->name('students.bulk-data.template');
    Route::post('students/bulk-data/import', [StudentController::class, 'bulkDataImport'])->name('students.bulk-data.import');
    Route::resource('teachers', TeacherController::class);
    Route::resource('students', StudentController::class);
    Route::get('admissions/settings/documents', [AdmissionDocumentSettingsController::class, 'index'])->name('admissions.settings.documents');
    Route::put('admissions/settings/documents', [AdmissionDocumentSettingsController::class, 'update'])->name('admissions.settings.documents.update');
    Route::get('admissions/visits', [AdmissionApplicationController::class, 'visits'])->name('admissions.visits.index');
    Route::resource('admissions', AdmissionApplicationController::class)->only(['index', 'show']);
    Route::post('admissions/{admission}/decision/accept', [AdmissionApplicationController::class, 'accept'])->name('admissions.decision.accept');
    Route::post('admissions/{admission}/decision/reject', [AdmissionApplicationController::class, 'reject'])->name('admissions.decision.reject');
    Route::post('admissions/{admission}/decision/waitlist', [AdmissionApplicationController::class, 'waitlist'])->name('admissions.decision.waitlist');
    Route::post('admissions/{admission}/decision/withdraw', [AdmissionApplicationController::class, 'withdraw'])->name('admissions.decision.withdraw');
    Route::post('admissions/{admission}/convert', [AdmissionApplicationController::class, 'convertToStudent'])->name('admissions.convert');
    Route::post('admissions/{admission}/stage', [AdmissionApplicationController::class, 'transitionStage'])->name('admissions.stage');
    Route::post('admissions/{admission}/assign', [AdmissionApplicationController::class, 'assignOfficer'])->name('admissions.assign');
    Route::post('admissions/{admission}/notes', [AdmissionApplicationController::class, 'storeNote'])->name('admissions.notes.store');
    Route::patch('admissions/{admission}/applicants/{applicant}', [AdmissionApplicationController::class, 'updateApplicant'])->name('admissions.applicants.update');
    Route::patch('admissions/{admission}/contacts/{contact}', [AdmissionApplicationController::class, 'updateContact'])->name('admissions.contacts.update');
    Route::patch('admissions/{admission}/visits/{visit}', [AdmissionApplicationController::class, 'updateVisit'])->name('admissions.visits.update');
    Route::patch('admissions/{admission}/documents/{document}', [AdmissionApplicationController::class, 'updateDocument'])->name('admissions.documents.update');
    Route::patch('admissions/{admission}/documents/{document}/review', [AdmissionApplicationController::class, 'reviewDocument'])->name('admissions.documents.review');
    Route::post('admissions/{admission}/documents/{document}/upload', [AdmissionApplicationController::class, 'uploadDocument'])->name('admissions.documents.upload');
    Route::delete('admissions/{admission}/documents/{document}/file', [AdmissionApplicationController::class, 'removeDocumentFile'])->name('admissions.documents.remove-file');
    Route::get('admissions/{admission}/documents/{document}/download', [AdmissionApplicationController::class, 'downloadDocument'])->name('admissions.documents.download');
    Route::post('students/{student}/lifecycle/promote', [\App\Http\Controllers\admin\StudentLifecycleController::class, 'promote'])->name('students.lifecycle.promote');
    Route::post('students/{student}/lifecycle/transfer', [\App\Http\Controllers\admin\StudentLifecycleController::class, 'transfer'])->name('students.lifecycle.transfer');
    Route::post('students/{student}/lifecycle/withdraw', [\App\Http\Controllers\admin\StudentLifecycleController::class, 'withdraw'])->name('students.lifecycle.withdraw');
    Route::post('students/{student}/lifecycle/re-enroll', [\App\Http\Controllers\admin\StudentLifecycleController::class, 'reEnroll'])->name('students.lifecycle.re-enroll');
    Route::post('students/{student}/lifecycle/graduate', [\App\Http\Controllers\admin\StudentLifecycleController::class, 'graduate'])->name('students.lifecycle.graduate');
    Route::post('students/{student}/lifecycle/status', [\App\Http\Controllers\admin\StudentLifecycleController::class, 'changeStatus'])->name('students.lifecycle.status');
    Route::post('students/{student}/lifecycle/guardians', [\App\Http\Controllers\admin\StudentLifecycleController::class, 'updateGuardians'])->name('students.lifecycle.guardians');
    Route::get('parents/bulk-data', [\App\Http\Controllers\admin\ParentController::class, 'bulkData'])->name('parents.bulk-data');
    Route::get('parents/bulk-data/template', [\App\Http\Controllers\admin\ParentController::class, 'bulkDataTemplate'])->name('parents.bulk-data.template');
    Route::post('parents/bulk-data/import', [\App\Http\Controllers\admin\ParentController::class, 'bulkDataImport'])->name('parents.bulk-data.import');
    Route::resource('parents', \App\Http\Controllers\admin\ParentController::class);
    Route::get('/timetable', [TimetableController::class, 'edit'])->name('timetable.edit');
    Route::put('/timetable', [TimetableController::class, 'update'])->name('timetable.update');
    Route::post('/timetable/save-framework', [TimetableController::class, 'saveFramework'])->name('timetable.save-framework');
    Route::get('/timetable/show', [TimetableController::class, 'show'])->name('timetable.show');
    Route::get('/timetable/periods/list', [TimetableController::class, 'listPeriods'])->name('timetable.periods.list');
    Route::post('/timetable/days', [TimetableController::class, 'addDay'])->name('timetable.days.add');
    Route::put('/timetable/days/{id}', [TimetableController::class, 'updateDay'])->name('timetable.days.update');
    Route::delete('/timetable/days/{id}', [TimetableController::class, 'deleteDay'])->name('timetable.days.delete');
    Route::post('/timetable/days/reorder', [TimetableController::class, 'reorderDays'])->name('timetable.days.reorder');
    Route::post('/timetable/periods', [TimetableController::class, 'addPeriod'])->name('timetable.periods.add');
    Route::put('/timetable/periods/{id}', [TimetableController::class, 'updatePeriod'])->name('timetable.periods.update');
    Route::delete('/timetable/periods/{id}', [TimetableController::class, 'deletePeriod'])->name('timetable.periods.delete');
    Route::get('/timetable/periods/{id}/create-lesson', [TimetableController::class, 'createLessonFromPeriod'])->name('timetable.periods.create-lesson');
    Route::post('/timetable/assign-teacher', [TimetableController::class, 'assignTeacher'])->name('timetable.assign-teacher');
    Route::delete('/timetable/assignments/{id}', [TimetableController::class, 'removeAssignment'])->name('timetable.assignments.remove');
    Route::get('/timetable/filters/backup', [TimetableController::class, 'filterBackupAssignments'])->name('timetable.filters.backup');
    Route::get('/timetable/filters/teacher/{id}', [TimetableController::class, 'filterTeacherSchedule'])->name('timetable.filters.teacher');
    Route::get('/timetable/filters/backup-report', [TimetableController::class, 'filterBackupByDateRange'])->name('timetable.filters.backup-report');
    Route::get('/timetable/daily-coverage/preview', [\App\Http\Controllers\admin\DailyAbsenceCoverageController::class, 'preview'])->name('timetable.daily-coverage.preview');
    Route::post('/timetable/daily-coverage/save-draft', [\App\Http\Controllers\admin\DailyAbsenceCoverageController::class, 'saveDraft'])->name('timetable.daily-coverage.save-draft');
    Route::post('/timetable/daily-coverage/approve', [\App\Http\Controllers\admin\DailyAbsenceCoverageController::class, 'approve'])->name('timetable.daily-coverage.approve');
    Route::match(['get', 'post'], '/timetable/daily-coverage/distribution-report', [\App\Http\Controllers\admin\DailyAbsenceCoverageController::class, 'distributionReport'])->name('timetable.daily-coverage.distribution-report');
    Route::post('/timetable/daily-coverage/notify-substitute', [\App\Http\Controllers\admin\DailyAbsenceCoverageController::class, 'notifySubstitute'])->name('timetable.daily-coverage.notify-substitute');
    Route::post('/timetable/daily-coverage/mark-absent', [\App\Http\Controllers\admin\DailyAbsenceCoverageController::class, 'markTeacherAbsent'])->name('timetable.daily-coverage.mark-absent');
    Route::post('/timetable/daily-coverage/close', [\App\Http\Controllers\admin\DailyAbsenceCoverageController::class, 'close'])->name('timetable.daily-coverage.close');
    Route::get('/timetable/daily-coverage/swap-candidates', [\App\Http\Controllers\admin\DailyAbsenceCoverageController::class, 'swapCandidates'])->name('timetable.daily-coverage.swap-candidates');
    Route::post('/timetable/daily-coverage/swap-preview', [\App\Http\Controllers\admin\DailyAbsenceCoverageController::class, 'swapPreview'])->name('timetable.daily-coverage.swap-preview');
    Route::post('/timetable/daily-coverage/apply-swap', [\App\Http\Controllers\admin\DailyAbsenceCoverageController::class, 'applySwap'])->name('timetable.daily-coverage.apply-swap');
    Route::post('/timetable/daily-coverage/cancel-lesson', [\App\Http\Controllers\admin\DailyAbsenceCoverageController::class, 'cancelLesson'])->name('timetable.daily-coverage.cancel-lesson');

    Route::post('/absence/demo-data', [TeacherAbsenceDemoController::class, 'store'])->name('absence.demo-data');

    // Attendance
    Route::prefix('attendances')->name('attendances.')->group(function () {
        Route::get('/dashboard', [AttendanceController::class, 'dashboard'])->name('dashboard');
        Route::get('/', [AttendanceController::class, 'index'])->name('index');
        Route::get('/mark', [AttendanceController::class, 'markForm'])->name('mark.form');
        Route::post('/mark', [AttendanceController::class, 'mark'])->name('mark');
        Route::post('/bulk-upload', [AttendanceController::class, 'bulkUpload'])->name('bulk-upload');
        Route::get('/import/{batch}', [AttendanceController::class, 'importPreview'])->name('import.preview');
        Route::post('/import/{batch}/confirm', [AttendanceController::class, 'importConfirm'])->name('import.confirm');
        Route::get('/thresholds', [AttendanceController::class, 'thresholds'])->name('thresholds');
        Route::post('/thresholds', [AttendanceController::class, 'storeThreshold'])->name('thresholds.store');
        Route::get('/alerts', [AttendanceController::class, 'alerts'])->name('alerts');
        Route::post('/alerts/{alert}/acknowledge', [AttendanceController::class, 'acknowledgeAlert'])->name('alerts.acknowledge');
        Route::post('/check-thresholds', [AttendanceController::class, 'runThresholdCheck'])->name('check-thresholds');
    });
    Route::get('students/{student}/attendance', [AttendanceController::class, 'studentTab'])->name('students.attendance');

    // Website Management (School Talent CMS)
    Route::prefix('website')->name('website.')->group(function () {
        Route::get('/', [WebsiteDashboardController::class, 'index'])->name('index');
        Route::post('/import-defaults', [WebsiteDashboardController::class, 'importDefaults'])->name('import-defaults');

        Route::get('/landing-settings', [WebsiteSettingsController::class, 'landing'])->name('landing');
        Route::put('/landing-settings', [WebsiteSettingsController::class, 'updateLanding'])->name('landing.update');

        Route::get('/chrome', [WebsiteChromeController::class, 'edit'])->name('chrome.edit');
        Route::match(['put', 'post'], '/chrome', [WebsiteChromeController::class, 'update'])->name('chrome.update');
        Route::get('/nav-links', [WebsiteNavLinkController::class, 'index'])->name('nav-links.index');
        Route::post('/nav-links', [WebsiteNavLinkController::class, 'store'])->name('nav-links.store');
        Route::put('/nav-links/{navLink}', [WebsiteNavLinkController::class, 'update'])->name('nav-links.update');
        Route::delete('/nav-links/{navLink}', [WebsiteNavLinkController::class, 'destroy'])->name('nav-links.destroy');
        Route::put('/nav-links-reorder', [WebsiteNavLinkController::class, 'reorder'])->name('nav-links.reorder');
        Route::get('/announcements', [WebsiteAnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('/announcements', [WebsiteAnnouncementController::class, 'store'])->name('announcements.store');
        Route::put('/announcements/{announcement}', [WebsiteAnnouncementController::class, 'update'])->name('announcements.update');
        Route::delete('/announcements/{announcement}', [WebsiteAnnouncementController::class, 'destroy'])->name('announcements.destroy');
        Route::put('/announcements-badge', [WebsiteAnnouncementController::class, 'updateBadge'])->name('announcements.badge');
        Route::get('/ctas', [WebsiteCtaController::class, 'index'])->name('ctas.index');
        Route::put('/ctas', [WebsiteCtaController::class, 'update'])->name('ctas.update');
        Route::get('/ui-labels', [WebsiteUiLabelsController::class, 'edit'])->name('ui-labels.edit');
        Route::put('/ui-labels', [WebsiteUiLabelsController::class, 'update'])->name('ui-labels.update');
        Route::get('/content-blocks', [WebsiteContentBlockController::class, 'index'])->name('content-blocks.index');
        Route::get('/content-blocks/{block}', [WebsiteContentBlockController::class, 'edit'])->name('content-blocks.edit');
        Route::put('/content-blocks/{block}', [WebsiteContentBlockController::class, 'update'])->name('content-blocks.update');
        Route::get('/gallery', [WebsiteGalleryController::class, 'index'])->name('gallery.index');
        Route::post('/gallery', [WebsiteGalleryController::class, 'store'])->name('gallery.store');
        Route::match(['put', 'post'], '/gallery/{gallery}', [WebsiteGalleryController::class, 'update'])->name('gallery.update');
        Route::delete('/gallery/{gallery}', [WebsiteGalleryController::class, 'destroy'])->name('gallery.destroy');

        Route::prefix('landing-builder')->name('landing-builder.')->group(function () {
            Route::get('/', [WebsiteLandingBuilderController::class, 'index'])->name('index');
            Route::get('/preview', [WebsiteLandingBuilderController::class, 'preview'])->name('preview');
            Route::post('/sections', [WebsiteLandingBuilderController::class, 'storeSection'])->name('sections.store');
            Route::put('/reorder', [WebsiteLandingBuilderController::class, 'reorder'])->name('reorder');
            Route::post('/publish', [WebsiteLandingBuilderController::class, 'publish'])->name('publish');
            Route::put('/status', [WebsiteLandingBuilderController::class, 'setStatus'])->name('status');
            Route::post('/revisions', [WebsiteLandingBuilderController::class, 'saveRevision'])->name('revisions.store');
            Route::post('/revisions/{revision}/restore', [WebsiteLandingBuilderController::class, 'restoreRevision'])->name('revisions.restore');
            Route::get('/sections/{section}/edit', [WebsiteLandingBuilderController::class, 'edit'])->name('edit');
            Route::put('/sections/{section}', [WebsiteLandingBuilderController::class, 'update'])->name('sections.update');
            Route::post('/sections/{section}/duplicate', [WebsiteLandingBuilderController::class, 'duplicate'])->name('sections.duplicate');
            Route::delete('/sections/{section}', [WebsiteLandingBuilderController::class, 'destroy'])->name('sections.destroy');
        });
        Route::get('/hero', [WebsiteSettingsController::class, 'hero'])->name('hero');
        Route::match(['put', 'post'], '/hero', [WebsiteSettingsController::class, 'updateHero'])->name('hero.update');
        Route::get('/school-info', [WebsiteSettingsController::class, 'schoolInfo'])->name('school-info');
        Route::match(['put', 'post'], '/school-info', [WebsiteSettingsController::class, 'updateSchoolInfo'])->name('school-info.update');
        Route::get('/admissions', [WebsiteSettingsController::class, 'admissions'])->name('admissions');
        Route::put('/admissions', [WebsiteSettingsController::class, 'updateAdmissions'])->name('admissions.update');
        Route::get('/contact', [WebsiteSettingsController::class, 'contact'])->name('contact');
        Route::put('/contact', [WebsiteSettingsController::class, 'updateContact'])->name('contact.update');
        Route::get('/social', [WebsiteSettingsController::class, 'social'])->name('social');
        Route::put('/social', [WebsiteSettingsController::class, 'updateSocial'])->name('social.update');
        Route::get('/seo', [WebsiteSettingsController::class, 'seo'])->name('seo');
        Route::match(['put', 'post'], '/seo', [WebsiteSettingsController::class, 'updateSeo'])->name('seo.update');
        Route::get('/theme', [WebsiteSettingsController::class, 'theme'])->name('theme');
        Route::match(['put', 'post'], '/theme', [WebsiteSettingsController::class, 'updateTheme'])->name('theme.update');

        Route::resource('stages', WebsiteStageController::class)->except(['show', 'update']);
        Route::match(['put', 'patch', 'post'], 'stages/{stage}', [WebsiteStageController::class, 'update'])->name('stages.update');
        Route::resource('facilities', WebsiteFacilityController::class)->except(['show', 'update']);
        Route::match(['put', 'patch', 'post'], 'facilities/{facility}', [WebsiteFacilityController::class, 'update'])->name('facilities.update');
        Route::resource('events', WebsiteEventController::class)->except(['show', 'update']);
        Route::match(['put', 'patch', 'post'], 'events/{event}', [WebsiteEventController::class, 'update'])->name('events.update');
        Route::resource('posts', WebsitePostController::class)->except(['show', 'update']);
        Route::match(['put', 'patch', 'post'], 'posts/{post}', [WebsitePostController::class, 'update'])->name('posts.update');
        Route::resource('testimonials', WebsiteTestimonialController::class)->except(['show', 'update']);
        Route::match(['put', 'patch', 'post'], 'testimonials/{testimonial}', [WebsiteTestimonialController::class, 'update'])->name('testimonials.update');
        Route::resource('success-stories', WebsiteSuccessStoryController::class)->except(['show', 'update']);
        Route::match(['put', 'patch', 'post'], 'success-stories/{success_story}', [WebsiteSuccessStoryController::class, 'update'])->name('success-stories.update');
        Route::resource('careers', WebsiteCareerController::class)->except(['show', 'update']);
        Route::match(['put', 'patch', 'post'], 'careers/{career}', [WebsiteCareerController::class, 'update'])->name('careers.update');
        Route::match(['put', 'post'], '/careers/recruitment', [WebsiteCareerController::class, 'updateRecruitment'])->name('careers.recruitment');

        Route::get('/media', [WebsiteMediaController::class, 'index'])->name('media.index');
        Route::post('/media', [WebsiteMediaController::class, 'store'])->name('media.store');
        Route::delete('/media/{medium}', [WebsiteMediaController::class, 'destroy'])->name('media.destroy');
        Route::get('/media/picker', [WebsiteMediaController::class, 'picker'])->name('media.picker');
    });

    // Dova Knowledge Center
    Route::prefix('dova-knowledge')->name('dova-knowledge.')->middleware('dova-knowledge')->group(function () {
        Route::get('/', [DovaKnowledgeController::class, 'dashboard'])->name('dashboard');
        Route::get('/sources', [DovaKnowledgeController::class, 'sources'])->name('sources.index');
        Route::post('/sources/{source}/toggle', [DovaKnowledgeController::class, 'toggleSource'])->name('sources.toggle');
        Route::post('/sources/{source}/reindex', [DovaKnowledgeController::class, 'reindexSource'])->name('sources.reindex');
        Route::get('/sources/{source}/records', [DovaKnowledgeController::class, 'sourceRecords'])->name('sources.records');
        Route::get('/sync', [DovaKnowledgeController::class, 'syncCenter'])->name('sync.index');
        Route::post('/sync/{group}', [DovaKnowledgeController::class, 'runSync'])->name('sync.run');
        Route::get('/explorer', [DovaKnowledgeController::class, 'explorer'])->name('explorer.index');
        Route::get('/testing', [DovaKnowledgeController::class, 'testing'])->name('testing.index');
        Route::post('/testing', [DovaKnowledgeController::class, 'runTest'])->name('testing.run');
        Route::get('/unanswered', [DovaUnansweredController::class, 'index'])->name('unanswered.index');
        Route::post('/unanswered/sync', [DovaUnansweredController::class, 'sync'])->name('unanswered.sync');
        Route::get('/unanswered/{gap}', [DovaUnansweredController::class, 'show'])->name('unanswered.show');
        Route::post('/unanswered/{gap}/draft', [DovaUnansweredController::class, 'saveDraft'])->name('unanswered.draft');
        Route::post('/unanswered/{gap}/publish', [DovaUnansweredController::class, 'publish'])->name('unanswered.publish');
        Route::post('/unanswered/{gap}/ignore', [DovaUnansweredController::class, 'ignore'])->name('unanswered.ignore');
        Route::get('/governance', [DovaFaqGovernanceController::class, 'index'])->name('governance.index');
        Route::get('/analytics', [DovaKnowledgeController::class, 'analytics'])->name('analytics.index');
        Route::get('/ai-usage', [DovaAiController::class, 'index'])->name('ai-usage.index');

        Route::prefix('faqs')->name('faqs.')->group(function () {
            Route::get('/dashboard', [DovaFaqController::class, 'dashboard'])->name('dashboard');
            Route::get('/', [DovaFaqController::class, 'index'])->name('index');
            Route::get('/create', [DovaFaqController::class, 'create'])->name('create');
            Route::post('/', [DovaFaqController::class, 'store'])->name('store');
            Route::get('/{faq}/edit', [DovaFaqController::class, 'edit'])->name('edit');
            Route::put('/{faq}', [DovaFaqController::class, 'update'])->name('update');
            Route::delete('/{faq}', [DovaFaqController::class, 'destroy'])->name('destroy');
            Route::post('/{faq}/review', [DovaFaqController::class, 'submitReview'])->name('review');
            Route::post('/{faq}/publish', [DovaFaqController::class, 'publish'])->name('publish');
            Route::post('/{faq}/archive', [DovaFaqController::class, 'archive'])->name('archive');
            Route::post('/{faq}/complete-review', [DovaFaqController::class, 'completeReview'])->name('complete-review');
            Route::post('/{faq}/deprecate', [DovaFaqController::class, 'deprecate'])->name('deprecate');
        });

        Route::prefix('gaps')->name('gaps.')->group(function () {
            Route::get('/', [DovaKnowledgeGapController::class, 'index'])->name('index');
            Route::post('/sync', [DovaKnowledgeGapController::class, 'sync'])->name('sync');
            Route::post('/{gap}/dismiss', [DovaKnowledgeGapController::class, 'dismiss'])->name('dismiss');
            Route::get('/{gap}/create-faq', [DovaKnowledgeGapController::class, 'createFaq'])->name('create-faq');
        });
    });
});

// Teacher Routes
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'teacher',
])->prefix('teacher')->as('teacher.')->group(function () {

    // Dashboard
    Route::get('/dashboard', [TeacherDashboardController::class, 'index'])->name('dashboard.index');

    // Timetable
    Route::get('/timetables', [TeacherTimetableController::class, 'index'])->name('timetables.index');
    Route::get('/timetables/grid', [TeacherTimetableController::class, 'grid'])->name('timetables.grid');
    Route::post('/timetables/assign-self', [TeacherTimetableController::class, 'assignSelf'])->name('timetables.assign-self');

    // Live Streams
    Route::get('/live-streams/{liveStream}/room', [TeacherLiveStreamController::class, 'room'])->name('live-streams.room');
    Route::patch('/live-streams/{liveStream}/status', [TeacherLiveStreamController::class, 'updateStatus'])->name('live-streams.update-status');
    // Recording
    Route::post('/live-streams/{liveStream}/upload-recording', [TeacherLiveStreamController::class, 'uploadRecording'])->name('live-streams.upload-recording');
    Route::post('/live-streams/{liveStream}/upload-wb-media', [TeacherLiveStreamController::class, 'uploadWbMedia'])->name('live-streams.upload-wb-media');
    Route::patch('/live-streams/{liveStream}/video-url', [TeacherLiveStreamController::class, 'submitVideoUrl'])->name('live-streams.video-url');
    // Extension
    Route::post('/live-streams/{liveStream}/request-extension', [TeacherLiveStreamController::class, 'requestExtension'])->name('live-streams.request-extension');
    Route::get('/live-streams/{liveStream}/remaining-seconds', [TeacherLiveStreamController::class, 'remainingSeconds'])->name('live-streams.remaining-seconds');
    // Quiz routes (teacher)
    Route::get('/live-streams/{liveStream}/quiz', [\App\Http\Controllers\admin\LiveStreamQuizController::class, 'index'])->name('live-streams.quiz.index');
    Route::post('/live-streams/{liveStream}/quiz', [\App\Http\Controllers\admin\LiveStreamQuizController::class, 'store'])->name('live-streams.quiz.store');
    Route::patch('/live-streams/{liveStream}/quiz/set-time', [\App\Http\Controllers\admin\LiveStreamQuizController::class, 'setTime'])->name('live-streams.quiz.set-time');
    Route::put('/live-streams/{liveStream}/quiz/{quiz}', [\App\Http\Controllers\admin\LiveStreamQuizController::class, 'update'])->name('live-streams.quiz.update');
    Route::delete('/live-streams/{liveStream}/quiz/{quiz}', [\App\Http\Controllers\admin\LiveStreamQuizController::class, 'destroy'])->name('live-streams.quiz.destroy');
    Route::patch('/live-streams/{liveStream}/quiz/{quiz}/activate', [\App\Http\Controllers\admin\LiveStreamQuizController::class, 'activate'])->name('live-streams.quiz.activate');
    Route::patch('/live-streams/{liveStream}/quiz/{quiz}/close', [\App\Http\Controllers\admin\LiveStreamQuizController::class, 'close'])->name('live-streams.quiz.close');
    Route::get('/live-streams/{liveStream}/quiz/{quiz}/answers', [\App\Http\Controllers\admin\LiveStreamQuizController::class, 'answers'])->name('live-streams.quiz.answers');
    // Exam routes (teacher)
    Route::post('/live-streams/{liveStream}/exam/launch', [\App\Http\Controllers\admin\LiveStreamExamController::class, 'launch'])->name('live-streams.exam.launch');
    Route::patch('/live-streams/{liveStream}/exam/{session}/close', [\App\Http\Controllers\admin\LiveStreamExamController::class, 'close'])->name('live-streams.exam.close');
    Route::get('/live-streams/{liveStream}/exam/{session}/status', [\App\Http\Controllers\admin\LiveStreamExamController::class, 'status'])->name('live-streams.exam.status');
    Route::resource('live-streams', TeacherLiveStreamController::class);

    Route::prefix('attendances')->name('attendances.')->group(function () {
        Route::get('/', [TeacherAttendanceController::class, 'index'])->name('index');
        Route::get('/class/{period}', [TeacherAttendanceController::class, 'class'])->name('class');
        Route::post('/class/{period}/mark', [TeacherAttendanceController::class, 'mark'])->name('mark');
    });

    // Lessons (teacher)
    Route::get('/lessons', [\App\Http\Controllers\teacher\LessonController::class, 'index'])->name('lessons.index');
    Route::get('/lessons/create', [\App\Http\Controllers\teacher\LessonController::class, 'create'])->name('lessons.create');
    Route::post('/lessons', [\App\Http\Controllers\teacher\LessonController::class, 'store'])->name('lessons.store');
    Route::get('/lessons/{lesson}/edit', [\App\Http\Controllers\teacher\LessonController::class, 'edit'])->name('lessons.edit');
    Route::get('/lessons/{lesson}/details', [\App\Http\Controllers\teacher\LessonController::class, 'editDetails'])->name('lessons.details');
    Route::put('/lessons/{lesson}', [\App\Http\Controllers\teacher\LessonController::class, 'update'])->name('lessons.update');
    Route::post('/lectures', [\App\Http\Controllers\teacher\LectureController::class, 'store'])->name('lectures.store');
    Route::delete('/lectures/{lecture}', [\App\Http\Controllers\teacher\LectureController::class, 'destroy'])->name('lectures.destroy');
    Route::post('/files/upload-to-bunny', [\App\Http\Controllers\teacher\LessonFileController::class, 'uploadToBunny'])->name('files.uploadToBunny');
    Route::post('/files/save-youtube-link', [\App\Http\Controllers\teacher\LessonFileController::class, 'saveYoutubeLink'])->name('files.saveYoutubeLink');
    Route::post('/files/save-external-link', [\App\Http\Controllers\teacher\LessonFileController::class, 'saveExternalLink'])->name('files.saveExternalLink');
    Route::put('/files/{file}', [\App\Http\Controllers\teacher\LessonFileController::class, 'update'])->name('files.update');
    Route::delete('/files/{file}', [\App\Http\Controllers\teacher\LessonFileController::class, 'destroy'])->name('files.destroy');
    Route::get('/timetable/periods/{period}/create-lesson', [\App\Http\Controllers\teacher\LessonController::class, 'createFromPeriod'])->name('lessons.from-period');
    Route::get('/lessons/{subjectId}/subject-categories', function ($subjectId) {
        $controller = new \App\Http\Controllers\teacher\LessonController();
        return response()->json($controller->getSubjectCategoriesPublic((int) $subjectId, request()->user()));
    })->name('lessons.subject-categories');
    Route::get('/lesson-strategies', [\App\Http\Controllers\teacher\LessonMessageTemplateController::class, 'index'])
        ->name('lesson-strategies.index');
    Route::post('/lesson-strategies', [\App\Http\Controllers\teacher\LessonMessageTemplateController::class, 'store'])
        ->name('lesson-strategies.store');
});

// Guardian portal (parents)
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'guardian',
])->prefix('guardian')->as('guardian.')->group(function () {
    Route::get('/dashboard', [GuardianPortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/notifications', [GuardianPortalController::class, 'notificationSettings'])->name('notifications');

    Route::get('/students/{student}', [GuardianPortalController::class, 'childOverview'])->name('students.overview');
    Route::get('/students/{student}/attendance', [GuardianPortalController::class, 'childAttendance'])->name('students.attendance');
    Route::get('/students/{student}/grades', [GuardianPortalController::class, 'childGrades'])->name('students.grades');
    Route::get('/students/{student}/behavior', [GuardianPortalController::class, 'childBehavior'])->name('students.behavior');
    Route::get('/students/{student}/schedule', [GuardianPortalController::class, 'childSchedule'])->name('students.schedule');
    Route::get('/students/{student}/courses', [GuardianPortalController::class, 'childCourses'])->name('students.courses');
    Route::get('/students/{student}/wallet', [GuardianPortalController::class, 'childWallet'])->name('students.wallet');

    Route::get('/wallet', [GuardianPortalController::class, 'wallet'])->name('wallet');
    Route::post('/wallet/transfer', [GuardianPortalController::class, 'walletTransfer'])->name('wallet.transfer');

    Route::redirect('/attendance', '/guardian/dashboard')->name('attendance.index');
    Route::get('/students/{student}/attendance-legacy', fn ($student) => redirect()->route('guardian.students.attendance', $student))->name('attendance.show');
});

// Department plan (Admin + Department Head)
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'department-plan',
])->prefix('department-plan')->as('department-plan.')->group(function () {
    Route::get('/', [DepartmentPlanController::class, 'index'])->name('index');
    Route::post('/{plan}/items', [DepartmentPlanController::class, 'syncItems'])->name('items.sync');
    Route::post('/{plan}/staffing', [DepartmentPlanController::class, 'syncStaffing'])->name('staffing.sync');
    Route::post('/{plan}/activate', [DepartmentPlanController::class, 'activate'])->name('activate');
});
