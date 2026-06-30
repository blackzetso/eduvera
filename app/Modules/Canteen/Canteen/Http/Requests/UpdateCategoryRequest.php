<?php

namespace App\Modules\Canteen\Http\Requests;

use App\Modules\Canteen\Http\Requests\Concerns\AuthorizesCanteen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    use AuthorizesCanteen;

    public function authorize(): bool
    {
        return $this->canteenAllows('canteen.categories.manage');
    }

    public function rules(): array
    {
        $id = $this->route('category');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('canteen_categories', 'slug')->ignore($id)],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
