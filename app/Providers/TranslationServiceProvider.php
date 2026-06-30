<?php

namespace App\Providers;

use App\Services\Translation\BilingualAutoTranslationService;
use App\Services\Translation\BilingualFieldResolver;
use App\Services\Translation\Contracts\TranslationServiceInterface;
use App\Services\Translation\TranslationManager;
use Illuminate\Support\ServiceProvider;

class TranslationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TranslationManager::class);
        $this->app->singleton(BilingualFieldResolver::class);
        $this->app->singleton(BilingualAutoTranslationService::class);

        $this->app->bind(TranslationServiceInterface::class, function ($app) {
            return $app->make(TranslationManager::class)->active();
        });
    }

    public function boot(): void
    {
        //
    }
}
