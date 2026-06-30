<?php

namespace App\Providers;

use Inertia\Inertia;
use App\Models\Admission\AdmissionApplicant;
use App\Models\Admission\AdmissionApplication;
use App\Models\Admission\AdmissionContact;
use App\Models\Admission\AdmissionDocument;
use App\Models\Admission\AdmissionVisit;
use App\Models\Language;
use App\Models\LanguagePhrase;
use App\Support\ViteHotFileGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        app(ViteHotFileGuard::class)->discardStaleHotFileIfNeeded();

        RateLimiter::for('form-runtime-get', function (Request $request) {
            return Limit::perMinute(config('form-builder.rate_limits.runtime_get', 60))
                ->by($request->ip());
        });

        RateLimiter::for('form-submission-post', function (Request $request) {
            return Limit::perMinute(config('form-builder.rate_limits.submission_post', 10))
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('form-submission-read', function (Request $request) {
            return Limit::perMinute(config('form-builder.rate_limits.submission_get', 30))
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('form-submission-list', function (Request $request) {
            return Limit::perMinute(config('form-builder.rate_limits.submission_list', 30))
                ->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('form-submission-review', function (Request $request) {
            return Limit::perMinute(config('form-builder.rate_limits.status_patch', 20))
                ->by($request->user()?->id ?: $request->ip());
        });

        Route::bind('admission', fn (string $value) => AdmissionApplication::findOrFail($value));
        Route::bind('applicant', fn (string $value) => AdmissionApplicant::findOrFail($value));
        Route::bind('contact', fn (string $value) => AdmissionContact::findOrFail($value));
        Route::bind('visit', fn (string $value) => AdmissionVisit::findOrFail($value));
        Route::bind('document', fn (string $value) => AdmissionDocument::findOrFail($value));

        // Force HTTPS when APP_URL is HTTPS (for ngrok or production)
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Inertia::share([
            'auth' => function () {
                return [
                    'user' => Auth::user(),
                ];
            },
            'canLogin' => fn () => Route::has('login'),
            'canRegister' => fn () => Route::has('register'),
            'laravelVersion' => fn () => Application::VERSION,
            'phpVersion' => fn () => PHP_VERSION,

            'translations' => function () {
                try {
                    $locale = app()->getLocale();

                    return \App\Models\LanguagePhrase::query()
                        ->join('languages', 'languages.id', '=', 'language_phrases.language_id')
                        ->where('languages.code', $locale)
                        ->pluck('word', 'key');
                } catch (\Exception $e) {
                    \Log::error('Error loading translations: ' . $e->getMessage());
                    return [];
                }
            },
        ]);
    }
}
