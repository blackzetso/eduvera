<?php

namespace App\Services\Admission\Bridge;

use App\Exceptions\Admission\BridgeBindingAmbiguousException;
use App\Models\Form;
use App\Support\Admission\Bridge\AdmissionBindingDefinition;
use App\Support\Admission\Bridge\AdmissionBindingResolveResult;
use App\Support\Admission\Bridge\AdmissionBridgeConfig;
use App\Support\Admission\Bridge\BridgeErrorCode;
use Illuminate\Support\Collection;

class AdmissionBindingResolver
{
    public function __construct(
        protected AdmissionBridgeConfig $config,
    ) {}

    /**
     * @return Collection<int, AdmissionBindingDefinition>
     */
    public function enabledBindings(): Collection
    {
        return $this->config->bindings()->filter(
            fn (AdmissionBindingDefinition $binding) => $binding->enabled,
        );
    }

    public function resolveByFormId(int $formId): AdmissionBindingResolveResult
    {
        $this->assertUniqueEnabledFormIds();

        $matches = $this->enabledBindings()
            ->filter(fn (AdmissionBindingDefinition $binding) => $binding->formId === $formId)
            ->values();

        if ($matches->isEmpty()) {
            return AdmissionBindingResolveResult::notFound();
        }

        if ($matches->count() > 1) {
            throw new BridgeBindingAmbiguousException(
                $formId,
                $matches->pluck('bindingKey')->all(),
            );
        }

        $binding = $matches->first();

        return $this->validateBindingActive($binding);
    }

    public function resolveByBindingKey(string $bindingKey): AdmissionBindingResolveResult
    {
        $this->assertUniqueEnabledFormIds();

        $binding = $this->config->binding($bindingKey);

        if ($binding === null || ! $binding->enabled) {
            return AdmissionBindingResolveResult::inactive('binding_disabled', binding: $binding);
        }

        return $this->validateBindingActive($binding);
    }

    protected function validateBindingActive(AdmissionBindingDefinition $binding): AdmissionBindingResolveResult
    {
        if (! $binding->enabled) {
            return AdmissionBindingResolveResult::inactive('binding_disabled', binding: $binding);
        }

        if ($binding->formId === null) {
            return AdmissionBindingResolveResult::inactive('form_id_not_configured', binding: $binding);
        }

        $form = Form::query()->find($binding->formId);

        if ($form === null) {
            return AdmissionBindingResolveResult::inactive('form_not_found', binding: $binding);
        }

        if ($form->status !== 'enable') {
            return AdmissionBindingResolveResult::inactive('form_not_enabled', binding: $binding, form: $form);
        }

        if ($form->publication_status !== 'published') {
            return AdmissionBindingResolveResult::inactive('form_not_published', binding: $binding, form: $form);
        }

        if (! $this->config->enabled()) {
            return AdmissionBindingResolveResult::inactive('bridge_globally_disabled', binding: $binding, form: $form);
        }

        return AdmissionBindingResolveResult::resolved($binding, $form);
    }

    /**
     * BR1-BIND-001: at most one enabled binding per form_id.
     */
    public function assertUniqueEnabledFormIds(): void
    {
        $formIdToBindings = [];

        foreach ($this->enabledBindings() as $binding) {
            if ($binding->formId === null) {
                continue;
            }

            $formIdToBindings[$binding->formId][] = $binding->bindingKey;
        }

        foreach ($formIdToBindings as $formId => $bindingKeys) {
            if (count($bindingKeys) > 1) {
                throw new BridgeBindingAmbiguousException((int) $formId, $bindingKeys);
            }
        }
    }

    public function inactiveErrorCode(): string
    {
        return BridgeErrorCode::BINDING_INACTIVE;
    }
}
