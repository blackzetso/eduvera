<?php

namespace App\Modules\Canteen\Http\Requests;

use App\Modules\Canteen\Http\Requests\Concerns\AuthorizesCanteen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryAdjustmentRequest extends FormRequest
{
    use AuthorizesCanteen;

    public function authorize(): bool
    {
        return $this->canteenAllows('canteen.inventory.manage');
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'uuid', Rule::exists('canteen_products', 'id')],
            'type' => ['required', Rule::in(['opening_stock', 'purchase', 'adjustment', 'damage', 'return'])],
            'quantity_delta' => ['required', 'numeric', 'not_in:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'المنتج مطلوب.',
            'quantity_delta.required' => 'أدخل كمية التعديل.',
            'quantity_delta.not_in' => 'الكمية يجب ألا تكون صفراً.',
            'type.in' => 'نوع حركة المخزون غير صالح.',
        ];
    }
}
