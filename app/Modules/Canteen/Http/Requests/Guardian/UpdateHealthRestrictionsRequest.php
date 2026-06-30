<?php

namespace App\Modules\Canteen\Http\Requests\Guardian;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHealthRestrictionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->user_type === 'guardian';
    }

    public function rules(): array
    {
        return [
            'allergies' => ['nullable', 'array'],
            'allergies.*' => ['string', 'max:64'],
            'blocked_tags' => ['nullable', 'array'],
            'blocked_tags.*' => ['string', 'max:64'],
            'block_all_purchases' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
