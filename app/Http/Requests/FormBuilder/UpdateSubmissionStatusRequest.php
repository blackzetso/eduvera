<?php

namespace App\Http\Requests\FormBuilder;

use App\Support\FormBuilder\FormSubmissionStatus;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSubmissionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                'in:'.implode(',', [
                    FormSubmissionStatus::APPROVED,
                    FormSubmissionStatus::REJECTED,
                    FormSubmissionStatus::UNDER_REVIEW,
                    FormSubmissionStatus::DRAFT,
                ]),
            ],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
