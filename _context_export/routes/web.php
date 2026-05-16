<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\web\WebController;
use App\Http\Controllers\admin\FileController;
use App\Http\Controllers\admin\FormController;
use App\Http\Controllers\admin\LessonController;
use App\Http\Controllers\admin\LectureController;
use App\Http\Controllers\admin\SettingController;
use App\Http\Controllers\admin\CategoryController;
use App\Http\Controllers\admin\LanguageController;
use App\Http\Controllers\admin\DashboardController;
use App\Http\Controllers\admin\WalletController;
use App\Http\Controllers\admin\TimetableController;
use App\Http\Controllers\admin\SubjectController;
use App\Http\Controllers\admin\TeacherController;
use App\Http\Controllers\admin\StudentController;
use App\Http\Controllers\teacher\TimetableController as TeacherTimetableController;
use App\Http\Controllers\teacher\DashboardController as TeacherDashboardController;
use App\Http\Controllers\teacher\LiveStreamController as TeacherLiveStreamController;
use App\Http\Controllers\web\FormController as WebFormController;
use App\Http\Controllers\admin\LiveStreamController;
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
    $buildDirExists = is_dir(public_path('build'));
    $assetsDirExists = is_dir(public_path('build/assets'));
    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Build Check</title></head><body style="font-family:monospace; padding:20px; background:#f5f5f5;">';
    $html .= '<h1>تشخيص الـ Build على السيرفر</h1>';
    $html .= '<ul>';
    $html .= '<li><strong>Laravel يعمل:</strong> نعم</li>';
    $html .= '<li><strong>مسار الـ manifest:</strong> ' . $manifestPath . '</li>';
    $html .= '<li><strong>ملف manifest.json موجود:</strong> ' . ($manifestExists ? 'نعم' : '<span style="color:red">لا - ارفع مجلد public/build</span>') . '</li>';
    $html .= '<li><strong>مجلد build/ موجود:</strong> ' . ($buildDirExists ? 'نعم' : 'لا') . '</li>';
    $html .= '<li><strong>مجلد build/assets/ موجود:</strong> ' . ($assetsDirExists ? 'نعم' : 'لا') . '</li>';
    $html .= '<li><strong>APP_DEBUG:</strong> ' . (config('app.debug') ? 'true' : 'false') . '</li>';
    $html .= '</ul>';
    if (!$manifestExists) {
        $html .= '<p style="color:red;">رفع مجلد public/build كاملاً (بداخله manifest.json و assets/) إلى السيرفر في المسار public/build/</p>';
    }
    $html .= '</body></html>';
    return response($html)->header('Content-Type', 'text/html; charset=utf-8');
})->name('build.check');

// Public Routes (للطلاب والزوار)
Route::get('/', [WebController::class, 'home'])->name('home');

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

// Language Switcher
Route::get('set-locale/{locale}', function ($locale) {
    session(['locale' => $locale]);
    app()->setLocale($locale);
    return back();
});

Route::post('/change-language', function (Request $request) {
    $lang = $request->input('lang', 'en');
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
    Route::get('parents/bulk-data', [\App\Http\Controllers\admin\ParentController::class, 'bulkData'])->name('parents.bulk-data');
    Route::get('parents/bulk-data/template', [\App\Http\Controllers\admin\ParentController::class, 'bulkDataTemplate'])->name('parents.bulk-data.template');
    Route::post('parents/bulk-data/import', [\App\Http\Controllers\admin\ParentController::class, 'bulkDataImport'])->name('parents.bulk-data.import');
    Route::resource('parents', \App\Http\Controllers\admin\ParentController::class);
    Route::get('/timetable', [TimetableController::class, 'edit'])->name('timetable.edit');
    Route::put('/timetable', [TimetableController::class, 'update'])->name('timetable.update');
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
    Route::post('/timetables/assign-self', [TeacherTimetableController::class, 'assignSelf'])->name('timetables.assign-self');
    Route::delete('/timetables/assignments/{id}', [TeacherTimetableController::class, 'removeSelfAssignment'])->name('timetables.assignments.remove');

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
});
