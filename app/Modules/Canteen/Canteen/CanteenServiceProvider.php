<?php

namespace App\Modules\Canteen;

use App\Modules\Canteen\Services\CanteenSettingsService;
use App\Modules\Canteen\Integration\Adapters\LocalSnapshotStudentAdapter;
use App\Modules\Canteen\Integration\Adapters\PendingWalletSettlementAdapter;
use App\Modules\Canteen\Integration\Adapters\QueuedParentNotificationAdapter;
use App\Modules\Canteen\Integration\Contracts\ParentNotificationPort;
use App\Modules\Canteen\Integration\Contracts\StudentIdentityPort;
use App\Modules\Canteen\Integration\Contracts\WalletSettlementPort;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CanteenServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(base_path('config/canteen.php'), 'canteen');

        $this->app->singleton(CanteenSettingsService::class);
        $this->app->singleton(StudentIdentityPort::class, LocalSnapshotStudentAdapter::class);
        $this->app->singleton(WalletSettlementPort::class, PendingWalletSettlementAdapter::class);
        $this->app->singleton(ParentNotificationPort::class, QueuedParentNotificationAdapter::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/canteen'));

        if (! CanteenModule::enabled()) {
            return;
        }

        $middleware = [
            'web',
            'auth:sanctum',
            config('jetstream.auth_session'),
            'verified',
            'canteen',
        ];

        Route::middleware($middleware)
            ->prefix(CanteenModule::routePrefix())
            ->as('canteen.')
            ->group(base_path('routes/canteen/web.php'));

        Route::middleware($middleware)
            ->prefix(CanteenModule::routePrefix().'/api')
            ->as('canteen.api.')
            ->group(base_path('routes/canteen/api.php'));
    }
}
