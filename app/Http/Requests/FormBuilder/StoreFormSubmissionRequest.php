<?php

namespace App\Http\Requests\FormBuilder;

use App\Support\FormBuilder\FormSubmissionStatus;
use Illuminate\Foundation\Http\FormRequest;

class StoreFormSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('target_status')) {
            $this->merge(['target_status' => FormSubmissionStatus::SUBMITTED]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'locale' => ['nullable', 'string', 'in:ar,en'],
            'target_status' => ['nullable', 'string', 'in:'.FormSubmissionStatus::DRAFT.','.FormSubmissionStatus::SUBMITTED],
            'snapshot_hash' => ['nullable', 'string', 'max:128'],
            'submission_id' => ['nullable', 'integer', 'exists:form_submissions,id'],
            'data' => ['present', 'array'],
            'data.*' => ['nullable'],
        ];
    }
}
