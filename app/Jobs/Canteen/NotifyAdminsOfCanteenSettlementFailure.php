<?php

namespace App\Jobs\Canteen;

use App\Models\User;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Notifications\CanteenSettlementFailedNotification;
use App\Modules\Canteen\Support\CanteenPermission;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyAdminsOfCanteenSettlementFailure implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $saleId,
        public string $reason,
    ) {}

    public function handle(): void
    {
        $sale = Sale::query()->find($this->saleId);

        if (! $sale) {
            return;
        }

        $notification = new CanteenSettlementFailedNotification($sale, $this->reason);

        $adminEmail = config('canteen.notifications.admin_email');

        if ($adminEmail) {
            $admin = User::query()->where('email', $adminEmail)->first();

            if ($admin) {
                $admin->notify($notification);

                return;
            }
        }

        User::query()
            ->where('user_type', 'admin')
            ->get()
            ->filter(fn (User $user) => CanteenPermission::allows($user, 'canteen.transactions.view'))
            ->each(fn (User $user) => $user->notify($notification));
    }
}
