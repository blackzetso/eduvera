<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemTask extends Model
{
    protected $fillable = [
        'task_name',
        'last_run_at',
        'next_run_at',
        'run_interval',
        'is_enabled',
        'last_result',
    ];

    protected $casts = [
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'is_enabled' => 'boolean',
        'last_result' => 'array',
    ];

    /**
     * Get or create a task
     */
    public static function getTask(string $taskName, int $intervalSeconds = 86400): self
    {
        return self::firstOrCreate(
            ['task_name' => $taskName],
            [
                'run_interval' => $intervalSeconds,
                'is_enabled' => true,
                'next_run_at' => now(),
            ]
        );
    }

    /**
     * Check if task should run now
     */
    public function shouldRun(): bool
    {
        if (!$this->is_enabled) {
            return false;
        }

        // If never run before, should run
        if (!$this->last_run_at) {
            return true;
        }

        // If next_run_at is in the past, should run
        return $this->next_run_at && $this->next_run_at->isPast();
    }

    /**
     * Mark task as running and update timestamps
     */
    public function markAsRunning(): void
    {
        $this->update([
            'last_run_at' => now(),
            'next_run_at' => now()->addSeconds($this->run_interval),
        ]);
    }

    /**
     * Save the result of the task
     */
    public function saveResult(array $result): void
    {
        $this->update([
            'last_result' => $result,
        ]);
    }
}
