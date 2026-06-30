<?php

namespace App\Modules\Canteen\Http\Requests;

use App\Modules\Canteen\Http\Requests\Concerns\AuthorizesCanteen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    use AuthorizesCanteen;

    public function authorize(): bool
    {
        return $this->canteenAllows('canteen.products.manage');
    }

    public function rules(): array
    {
        $id = $this->route('product');

        return [
            'category_id' => ['sometimes', 'required', 'uuid', Rule::exists('canteen_categories', 'id')],
            'sku' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('canteen_products', 'sku')->ignore($id)],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('canteen_products', 'barcode')->ignore($id)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'unit' => ['sometimes', Rule::in(['piece', 'pack', 'serving'])],
            'selling_price' => ['sometimes', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_restricted_default' => ['nullable', 'boolean'],
            'restriction_tags' => ['nullable', 'array'],
            'restriction_tags.*' => ['string', 'max:50'],
            'image_path' => ['nullable', 'string', 'max:500'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return (new StoreProductRequest)->messages();
    }
}
