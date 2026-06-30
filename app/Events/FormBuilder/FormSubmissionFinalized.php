<?php

namespace App\Events\FormBuilder;

use App\Support\FormBuilder\FormSubmissionFinalizedPayload;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FormSubmissionFinalized
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public FormSubmissionFinalizedPayload $payload,
    ) {}
}
