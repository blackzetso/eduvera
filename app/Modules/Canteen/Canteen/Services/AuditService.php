<?php

namespace App\Modules\Canteen\Services;

use App\Modules\Canteen\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class AuditService
{
    public function log(
        string $action,
        Model $subject,
        ?array $before = null,
        ?array $after = null,
        ?int $actorUserId = null,
        ?string $ipAddress = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'actor_user_id' => $actorUserId ?? auth()->id(),
            'action' => $action,
            'subject_type' => $subject->getTable(),
            'subject_id' => $subject->getKey(),
            'before' => $before,
            'after' => $after,
            'ip_address' => $ipAddress ?? request()?->ip(),
            'created_at' => Carbon::now(),
        ]);
    }
}
