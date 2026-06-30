<?php

namespace App\Jobs;

use App\Services\LiveStreamAttendanceSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncLiveStreamAttendanceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ?int $liveStreamId = null,
    ) {}

    public function handle(LiveStreamAttendanceSyncService $syncService): void
    {
        $syncService->syncAll($this->liveStreamId);
    }
}
