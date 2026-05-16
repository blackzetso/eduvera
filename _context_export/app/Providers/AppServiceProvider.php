<?php

namespace App\Providers;

use Inertia\Inertia;
use App\Models\Language;
use App\Models\LanguagePhrase;
use Illuminate\Support\Facades\Auth;
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
