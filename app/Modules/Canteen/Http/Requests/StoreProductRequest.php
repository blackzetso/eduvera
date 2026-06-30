<?php

namespace App\Modules\Canteen\Http\Requests;

use App\Modules\Canteen\Http\Requests\Concerns\AuthorizesCanteen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    use AuthorizesCanteen;

    public function authorize(): bool
    {
        return $this->canteenAllows('canteen.products.manage');
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'uuid', Rule::exists('canteen_categories', 'id')],
            'sku' => ['required', 'string', 'max:100', Rule::unique('canteen_products', 'sku')],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('canteen_products', 'barcode')],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'unit' => ['required', Rule::in(['piece', 'pack', 'serving'])],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_restricted_default' => ['nullable', 'boolean'],
            'restriction_tags' => ['nullable', 'array'],
            'restriction_tags.*' => ['string', 'max:50'],
            'image_path' => ['nullable', 'string', 'max:500'],
            'metadata' => ['nullable', 'array'],
            'initial_stock' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'اختر تصنيفاً للمنتج.',
            'category_id.exists' => 'التصنيف المحدد غير صالح.',
            'sku.required' => 'رمز SKU مطلوب.',
            'sku.unique' => 'رمز SKU مستخدم مسبقاً.',
            'barcode.unique' => 'الباركود مستخدم مسبقاً.',
            'name.required' => 'اسم المنتج مطلوب.',
            'unit.required' => 'اختر وحدة المنتج.',
            'unit.in' => 'الوحدة يجب أن تكون: قطعة، عبوة، أو وجبة.',
            'selling_price.required' => 'سعر البيع مطلوب.',
            'selling_price.numeric' => 'سعر البيع يجب أن يكون رقماً.',
        ];
    }
}
