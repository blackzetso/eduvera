<?php

namespace App\Listeners\Admission\Bridge;

use App\Events\FormBuilder\FormSubmissionFinalized;
use App\Jobs\Admission\Bridge\ProcessAdmissionBridgeSubmissionJob;
use App\Services\Admission\Bridge\AdmissionBridgeConsumer;
use App\Support\Admission\Bridge\AdmissionBridgeConfig;

class ProcessFormSubmissionFinalizedForAdmissionBridge
{
    public function __construct(
        protected AdmissionBridgeConfig $config,
        protected AdmissionBridgeConsumer $consumer,
    ) {}

    public function handle(FormSubmissionFinalized $event): void
    {
        if (! $this->config->enabled()) {
            return;
        }

        if ($this->config->processingMode() === 'sync') {
            $this->consumer->consume($event->payload);

            return;
        }

        ProcessAdmissionBridgeSubmissionJob::dispatch($event->payload->toArray())
            ->onQueue($this->config->queueName());
    }
}
