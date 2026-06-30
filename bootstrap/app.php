<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\RunOpportunisticTasks::class, // Auto-run scheduled tasks
            \App\Http\Middleware\EnsureStandardsModeHtml::class, // Strip any output before <!DOCTYPE to avoid Quirks Mode
        ]);

        $middleware->alias([
            'admin'         => \App\Http\Middleware\EnsureAdmin::class,
            'teacher'       => \App\Http\Middleware\EnsureTeacher::class,
            'social-worker' => \App\Http\Middleware\EnsureSocialWorker::class,
            'nurse'         => \App\Http\Middleware\EnsureNurse::class,
            'control-staff' => \App\Http\Middleware\EnsureControlStaff::class,
            'card-reader'   => \App\Http\Middleware\EnsureCardReader::class,
            'guardian'      => \App\Http\Middleware\EnsureGuardian::class,
            'department-plan' => \App\Http\Middleware\EnsureAdminOrDepartmentHead::class,
            'dova-knowledge' => \App\Http\Middleware\EnsureDovaKnowledgeAccess::class,
            'admission-intake' => \App\Http\Middleware\SecureAdmissionIntake::class,
            
            'canteen' => \App\Modules\Canteen\Http\Middleware\EnsureCanteenModuleEnabled::class,
            'canteen.permission' => \App\Modules\Canteen\Http\Middleware\EnsureCanteenPermission::class,
            'canteen.guardian' => \App\Modules\Canteen\Http\Middleware\EnsureCanteenGuardianApi::class,
            'canteen.guardian.owns-student' => \App\Modules\Canteen\Http\Middleware\EnsureGuardianOwnsStudent::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withSchedule(function ($schedule) {
        // Sync Bunny consumption daily at 2 AM
        $schedule->command('bunny:sync-consumption')->dailyAt('02:00');
        
        // Update exchange rate every 6 hours (4 times daily = ~120 requests/month)
        $schedule->command('exchange-rate:update')->everySixHours();

        // Check FAQ review due dates and send owner reminders
        $schedule->command('dova:check-knowledge-review')->dailyAt('06:00');
    })
    ->create();
