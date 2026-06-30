<?php

namespace App\Support\Admission;

class AdmissionReadinessResult
{
    /**
     * @param  array<int, array{id: string, label: string, ok: bool, blocking: bool, severity: string}>  $checks
     */
    public function __construct(
        public bool $ready,
        public array $checks,
        public string $context,
    ) {}

    /**
     * @return array<int, string>
     */
    public function blockingErrors(): array
    {
        return collect($this->checks)
            ->filter(fn (array $check) => $check['blocking'] && ! $check['ok'])
            ->map(fn (array $check) => $check['label'])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function warnings(): array
    {
        return collect($this->checks)
            ->filter(fn (array $check) => ! $check['blocking'] && ! $check['ok'])
            ->map(fn (array $check) => $check['label'])
            ->values()
            ->all();
    }

    public function completionPercentage(): int
    {
        if ($this->checks === []) {
            return 0;
        }

        $completed = collect($this->checks)->where('ok', true)->count();

        return (int) round(($completed / count($this->checks)) * 100);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ready' => $this->ready,
            'errors' => $this->blockingErrors(),
            'blocking_errors' => $this->blockingErrors(),
            'warnings' => $this->warnings(),
            'checks' => $this->checks,
            'completion_percentage' => $this->completionPercentage(),
            'context' => $this->context,
            'contexts' => [
                AdmissionReadinessPolicy::CONTEXT_VISIT_SCHEDULE,
                AdmissionReadinessPolicy::CONTEXT_LEAD,
                AdmissionReadinessPolicy::CONTEXT_APPLICATION,
                AdmissionReadinessPolicy::CONTEXT_DECISION,
                AdmissionReadinessPolicy::CONTEXT_CONVERSION,
            ],
        ];
    }
}
