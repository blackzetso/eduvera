<?php

namespace App\Providers;

use App\Events\FormBuilder\FormSubmissionFinalized;
use App\Exceptions\Admission\BridgeBindingAmbiguousException;
use App\Listeners\Admission\Bridge\ProcessFormSubmissionFinalizedForAdmissionBridge;
use App\Services\Admission\Bridge\AdmissionBindingResolver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AdmissionBridgeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(
            FormSubmissionFinalized::class,
            ProcessFormSubmissionFinalizedForAdmissionBridge::class,
        );

        if (! config('admissions_bridge.enabled', false)) {
            return;
        }

        try {
            $this->app->make(AdmissionBindingResolver::class)->assertUniqueEnabledFormIds();
        } catch (BridgeBindingAmbiguousException $exception) {
            Log::critical('Admission bridge boot validation failed: ambiguous enabled bindings.', [
                'form_id' => $exception->formId,
                'binding_keys' => $exception->bindingKeys,
                'error_code' => $exception->errorCode(),
            ]);
        }
    }
}
