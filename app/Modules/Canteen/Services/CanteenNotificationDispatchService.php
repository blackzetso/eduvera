<?php

namespace App\Modules\Canteen\Services;

use App\Models\GuardianNotificationPreference;
use App\Models\User;
use App\Modules\Canteen\Models\ParentVisibilityQueue;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Notifications\CanteenPurchaseNotification;
use Illuminate\Support\Facades\Log;

class CanteenNotificationDispatchService
{
    public function __construct(
        protected CanteenWhatsAppNotifier $whatsApp,
        protected CanteenPurchaseGuardianSyncService $guardianSync,
    ) {}

    public function dispatchForSaleId(string $saleId): bool
    {
        $queue = ParentVisibilityQueue::query()->where('sale_id', $saleId)->first();

        if (! $queue) {
            return false;
        }

        return $this->dispatchForQueueEntry($queue);
    }

    public function dispatchForQueueEntry(ParentVisibilityQueue $queue): bool
    {
        if (in_array($queue->notification_status, ['sent', 'suppressed'], true)) {
            return false;
        }

        if ($queue->visibility_status === 'suppressed') {
            $queue->update(['notification_status' => 'suppressed']);

            return false;
        }

        $sale = Sale::query()
            ->with(['items', 'studentUser'])
            ->find($queue->sale_id);

        if (! $sale) {
            $queue->update([
                'notification_status' => 'failed',
                'notification_attempts' => $queue->notification_attempts + 1,
                'last_notification_error' => 'Sale not found.',
            ]);

            return false;
        }

        $this->guardianSync->syncSale($sale);
        $queue->refresh();

        $student = $sale->studentUser
            ?? User::query()->students()->find((int) $sale->student_id_ref);

        if (! $student) {
            $queue->update([
                'notification_status' => 'failed',
                'notification_attempts' => $queue->notification_attempts + 1,
                'last_notification_error' => 'Student not found.',
            ]);

            return false;
        }

        $guardians = $student->guardians()->get();

        if ($guardians->isEmpty()) {
            $queue->update([
                'notification_status' => 'failed',
                'notification_attempts' => $queue->notification_attempts + 1,
                'last_notification_error' => 'No guardians linked.',
            ]);

            return false;
        }

        try {
            foreach ($guardians as $guardian) {
                $this->notifyGuardian($guardian, $student, $sale, $queue);
            }

            $queue->update([
                'visibility_status' => 'published',
                'published_at' => $queue->published_at ?? now(),
                'notification_status' => 'sent',
                'notification_attempts' => $queue->notification_attempts + 1,
                'notified_at' => now(),
                'last_notification_error' => null,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Canteen purchase notification failed', [
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
            ]);

            $queue->update([
                'notification_status' => 'failed',
                'notification_attempts' => $queue->notification_attempts + 1,
                'last_notification_error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function notifyGuardian(
        User $guardian,
        User $student,
        Sale $sale,
        ParentVisibilityQueue $queue,
    ): void {
        $prefs = GuardianNotificationPreference::query()
            ->where('guardian_id', $guardian->id)
            ->where(fn ($q) => $q->where('student_id', $student->id)->orWhereNull('student_id'))
            ->orderByDesc('student_id')
            ->first();

        $allowCanteen = $prefs?->notify_canteen_purchase ?? true;
        $allowInApp = $prefs?->notify_in_app ?? true;
        $allowEmail = $prefs?->notify_email ?? false;
        $allowWhatsApp = $prefs?->notify_whatsapp ?? true;

        if (! $allowCanteen) {
            return;
        }

        if ($allowInApp || $allowEmail) {
            $notification = new CanteenPurchaseNotification($student, $sale, $queue->payload ?? []);
            $channels = [];

            if ($allowInApp) {
                $channels[] = 'database';
            }

            if ($allowEmail && $guardian->email) {
                $channels[] = 'mail';
            }

            if ($channels !== []) {
                $guardian->notify($notification->withChannels($channels));
            }
        }

        if ($allowWhatsApp) {
            $this->whatsApp->notifyPurchase($guardian, $student, $sale);
        }
    }
}
