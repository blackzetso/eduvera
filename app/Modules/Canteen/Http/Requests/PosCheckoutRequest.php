<?php

namespace App\Modules\Canteen\Http\Requests;

use App\Modules\Canteen\Http\Requests\Concerns\AuthorizesCanteen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PosCheckoutRequest extends FormRequest
{
    use AuthorizesCanteen;

    public function authorize(): bool
    {
        if (! $this->canteenAllows('canteen.pos.access')) {
            return false;
        }

        if ($this->boolean('limit_override') && ! $this->canteenAllows('canteen.student-limits.override')) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'student_id_ref' => ['required', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', Rule::exists('canteen_products', 'id')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'limit_override' => ['nullable', 'boolean'],
            'limit_override_reason' => ['required_if:limit_override,true', 'nullable', 'string', 'max:500'],
        ];
    }
}
