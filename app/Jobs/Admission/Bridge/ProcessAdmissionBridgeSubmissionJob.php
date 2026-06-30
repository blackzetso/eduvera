<?php

namespace App\Jobs\Admission\Bridge;

use App\Services\Admission\Bridge\AdmissionBridgeConsumer;
use App\Support\FormBuilder\FormSubmissionFinalizedPayload;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessAdmissionBridgeSubmissionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * @param  array<string, mixed>  $eventPayload
     */
    public function __construct(
        public array $eventPayload,
    ) {}

    public function handle(AdmissionBridgeConsumer $consumer): void
    {
        $consumer->consume(FormSubmissionFinalizedPayload::fromArray($this->eventPayload));
    }
}
