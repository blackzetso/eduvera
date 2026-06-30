<?php

namespace App\Console\Commands;

use App\Models\SystemTask;
use App\Services\LiveStreamAttendanceSyncService;
use Illuminate\Console\Command;

class SyncLiveStreamAttendances extends Command
{
    protected $signature = 'attendance:sync-live-stream {live_stream_id?}';

    protected $description = 'Sync live_stream_attendances into student_attendances';

    public function handle(LiveStreamAttendanceSyncService $syncService): int
    {
        $task = SystemTask::getTask('sync_live_stream_attendances', 3600);
        $task->markAsRunning();

        $liveStreamId = $this->argument('live_stream_id') ? (int) $this->argument('live_stream_id') : null;
        $count = $syncService->syncAll($liveStreamId);

        $task->saveResult([
            'success' => true,
            'synced' => $count,
            'ran_at' => now()->toIso8601String(),
        ]);

        $this->info("Synced {$count} live stream attendance record(s).");

        return self::SUCCESS;
    }
}
