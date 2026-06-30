<?php

namespace App\Services;

use App\Models\PlatformAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class PlatformAuditService
{
    public function record(
        string $domain,
        string $action,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        ?array $metadata = null,
        ?int $userId = null,
    ): PlatformAuditLog {
        return PlatformAuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'domain' => $domain,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'before_state' => $before,
            'after_state' => $after,
            'metadata' => $metadata,
            'ip_address' => Request::ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function timelineForSubject(Model $subject, int $limit = 50): array
    {
        return PlatformAuditLog::query()
            ->with('user:id,name')
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey())
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (PlatformAuditLog $log) => [
                'id' => $log->id,
                'domain' => $log->domain,
                'action' => $log->action,
                'action_label' => $this->actionLabel($log->domain, $log->action),
                'user_name' => $log->user?->name,
                'before_state' => $log->before_state,
                'after_state' => $log->after_state,
                'metadata' => $log->metadata,
                'occurred_at' => $log->created_at?->toDateTimeString(),
                'icon' => $this->actionIcon($log->domain, $log->action),
            ])
            ->all();
    }

    protected function actionLabel(string $domain, string $action): string
    {
        return config("platform_audit.labels.{$domain}.{$action}")
            ?? config("platform_audit.labels.{$action}")
            ?? $action;
    }

    protected function actionIcon(string $domain, string $action): string
    {
        return config("platform_audit.icons.{$domain}.{$action}")
            ?? 'bi-journal-text';
    }
}
