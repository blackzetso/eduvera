<?php

namespace App\Modules\Canteen\Http\Requests\Guardian;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuardianBlockedProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->user_type === 'guardian';
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'uuid', 'exists:canteen_products,id'],
            'restriction_type' => ['required', 'string', 'in:permanent,temporary'],
            'duration_days' => ['required_if:restriction_type,temporary', 'nullable', 'integer', 'min:1', 'max:365'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
