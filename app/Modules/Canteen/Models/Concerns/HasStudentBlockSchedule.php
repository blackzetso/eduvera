<?php

namespace App\Modules\Canteen\Models\Concerns;

use Illuminate\Support\Carbon;

trait HasStudentBlockSchedule
{
    public function scopeCurrentlyEffective($query)
    {
        $now = Carbon::now();

        return $query
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now));
    }

    public function scopeActive($query)
    {
        return $query->currentlyEffective();
    }

    public function isPermanent(): bool
    {
        return $this->expires_at === null;
    }

    public function isCurrentlyEffective(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = Carbon::now();

        if ($this->starts_at && $this->starts_at->gt($now)) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->lte($now)) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->lte(Carbon::now());
    }

    public function remainingDays(): ?int
    {
        if ($this->expires_at === null || $this->isExpired()) {
            return null;
        }

        return max(0, (int) Carbon::now()->startOfDay()->diffInDays($this->expires_at->copy()->startOfDay()));
    }
}
