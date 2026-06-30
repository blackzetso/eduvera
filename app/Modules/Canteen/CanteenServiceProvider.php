<?php

namespace App\Modules\Canteen;

use App\Modules\Canteen\Console\Commands\CanteenFullSyncCommand;
use App\Modules\Canteen\Console\Commands\CanteenSeedWalletsCommand;
use App\Modules\Canteen\Console\Commands\CanteenSyncAllCommand;
use App\Modules\Canteen\Console\Commands\CanteenIntegrationCheckCommand;
use App\Modules\Canteen\Console\Commands\CanteenIntegrationAuditCommand;
use App\Modules\Canteen\Console\Commands\CanteenSystemIntegrationAuditCommand;
use App\Modules\Canteen\Console\Commands\RegisterCanteenTeachersCommand;
use App\Modules\Canteen\Console\Commands\PublishCanteenPendingNotificationsCommand;
use App\Modules\Canteen\Console\Commands\ReconcileCanteenFinanceCommand;
use App\Modules\Canteen\Console\Commands\SyncCanteenGuardianLinksCommand;
use App\Modules\Canteen\Console\Commands\SyncCanteenPurchaseGuardiansCommand;
use App\Modules\Canteen\Events\CanteenSaleCompleted;
use App\Modules\Canteen\Events\CanteenSaleFailed;
use App\Modules\Canteen\Events\CanteenSaleVoided;
use App\Modules\Canteen\Integration\Contracts\FinanceIntegrationPort;
use App\Modules\Canteen\Integration\Contracts\GuardianIntegrationPort;
use App\Modules\Canteen\Integration\Contracts\ParentNotificationPort;
use App\Modules\Canteen\Integration\Contracts\StudentIdentityPort;
use App\Modules\Canteen\Integration\Contracts\WalletSettlementPort;
use App\Modules\Canteen\Listeners\DispatchCanteenAdminFailureNotifications;
use App\Modules\Canteen\Listeners\DispatchCanteenPurchaseNotifications;
use App\Modules\Canteen\Listeners\RecordCanteenFinanceEntry;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Observers\SaleObserver;
use App\Modules\Canteen\Services\CanteenFinanceBridgeService;
use App\Modules\Canteen\Services\CanteenFullSyncService;
use App\Modules\Canteen\Services\CanteenSyncAllService;
use App\Modules\Canteen\Services\CanteenStaffRegistrationService;
use App\Modules\Canteen\Services\CanteenHealthRestrictionBootstrapService;
use App\Modules\Canteen\Services\CanteenFinanceReconciliationService;
use App\Modules\Canteen\Services\CanteenGuardianProfileSyncService;
use App\Modules\Canteen\Services\CanteenGuardianSpendingService;
use App\Modules\Canteen\Services\CanteenHealthRestrictionService;
use App\Modules\Canteen\Services\CanteenNotificationDispatchService;
use App\Modules\Canteen\Services\CanteenParentDashboardService;
use App\Modules\Canteen\Services\CanteenPurchaseGuardianSyncService;
use App\Modules\Canteen\Services\CanteenSettingsService;
use App\Modules\Canteen\Services\CanteenStudentEligibilityService;
use App\Modules\Canteen\Services\CanteenStudentProfileSyncService;
use App\Modules\Canteen\Services\CanteenWhatsAppNotifier;
use App\Modules\Canteen\Services\GuardianCanteenStudentService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class CanteenServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(base_path('config/canteen.php'), 'canteen');

        $this->app->singleton(CanteenSettingsService::class);
        $this->app->singleton(CanteenStudentEligibilityService::class);
        $this->app->singleton(CanteenStudentProfileSyncService::class);
        $this->app->singleton(CanteenGuardianProfileSyncService::class);
        $this->app->singleton(CanteenPurchaseGuardianSyncService::class);
        $this->app->singleton(CanteenHealthRestrictionService::class);
        $this->app->singleton(CanteenGuardianSpendingService::class);
        $this->app->singleton(CanteenParentDashboardService::class);
        $this->app->singleton(GuardianCanteenStudentService::class);
        $this->app->singleton(CanteenWhatsAppNotifier::class);
        $this->app->singleton(CanteenNotificationDispatchService::class);
        $this->app->singleton(CanteenFinanceBridgeService::class);
        $this->app->singleton(CanteenFinanceReconciliationService::class);
        $this->app->singleton(CanteenHealthRestrictionBootstrapService::class);
        $this->app->singleton(CanteenSyncAllService::class);
        $this->app->alias(CanteenSyncAllService::class, CanteenFullSyncService::class);
        $this->app->singleton(CanteenStaffRegistrationService::class);

        $this->bindIntegrationAdapters();
    }

    public function boot(): void
    {
        Sale::observe(SaleObserver::class);
        $this->registerEventListeners();

        if ($this->app->runningInConsole()) {
            $this->commands([
                CanteenSyncAllCommand::class,
                CanteenSeedWalletsCommand::class,
                CanteenFullSyncCommand::class,
                RegisterCanteenTeachersCommand::class,
                CanteenIntegrationCheckCommand::class,
                CanteenIntegrationAuditCommand::class,
                CanteenSystemIntegrationAuditCommand::class,
                SyncCanteenGuardianLinksCommand::class,
                SyncCanteenPurchaseGuardiansCommand::class,
                PublishCanteenPendingNotificationsCommand::class,
                ReconcileCanteenFinanceCommand::class,
            ]);
        }

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

        Route::middleware([
            'web',
            'auth:sanctum',
            config('jetstream.auth_session'),
            'verified',
            'guardian',
            'canteen.guardian',
        ])
            ->prefix('guardian/canteen/api')
            ->as('guardian.canteen.api.')
            ->group(base_path('routes/canteen/guardian-api.php'));
    }

    protected function registerEventListeners(): void
    {
        Event::listen(CanteenSaleCompleted::class, [RecordCanteenFinanceEntry::class, 'handleCompleted']);
        Event::listen(CanteenSaleCompleted::class, DispatchCanteenPurchaseNotifications::class);
        Event::listen(CanteenSaleFailed::class, [RecordCanteenFinanceEntry::class, 'handleFailed']);
        Event::listen(CanteenSaleFailed::class, DispatchCanteenAdminFailureNotifications::class);
        Event::listen(CanteenSaleVoided::class, [RecordCanteenFinanceEntry::class, 'handleVoided']);
    }

    protected function bindIntegrationAdapters(): void
    {
        $this->app->singleton(StudentIdentityPort::class, function ($app) {
            return $app->make($this->resolveAdapterClass('student', 'student_adapter'));
        });

        $this->app->singleton(WalletSettlementPort::class, function ($app) {
            return $app->make($this->resolveAdapterClass('wallet', 'wallet_adapter'));
        });

        $this->app->singleton(GuardianIntegrationPort::class, function ($app) {
            return $app->make($this->resolveAdapterClass('guardian', 'guardian_adapter'));
        });

        $this->app->singleton(ParentNotificationPort::class, function ($app) {
            return $app->make($this->resolveAdapterClass('parent', 'parent_adapter'));
        });

        $this->app->singleton(FinanceIntegrationPort::class, function ($app) {
            return $app->make($this->resolveAdapterClass('finance', 'finance_adapter'));
        });
    }

    protected function resolveAdapterClass(string $group, string $configKey): string
    {
        $key = config("canteen.integration.{$configKey}");
        $class = config("canteen.adapters.{$group}.{$key}");

        if (! is_string($class) || $class === '' || ! class_exists($class)) {
            throw new InvalidArgumentException(
                "Invalid canteen adapter [{$group}={$key}]. Check CANTEEN_".strtoupper($configKey).' in .env.'
            );
        }

        return $class;
    }
}
