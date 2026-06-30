<?php

namespace App\Modules\Canteen\Http\Requests\Guardian;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGuardianDailyLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->user_type === 'guardian';
    }

    public function rules(): array
    {
        return [
            'daily_spending_limit' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
