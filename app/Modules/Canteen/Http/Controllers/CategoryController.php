<?php

namespace App\Modules\Canteen\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Canteen\Http\Requests\StoreCategoryRequest;
use App\Modules\Canteen\Http\Requests\UpdateCategoryRequest;
use App\Modules\Canteen\Http\Resources\CategoryResource;
use App\Modules\Canteen\Models\Category;
use App\Modules\Canteen\Services\CategoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function __construct(protected CategoryService $service) {}

    public function index(Request $request)
    {
        $paginator = $this->service->paginate($request->only(['search', 'is_active', 'per_page']));

        if ($request->wantsJson()) {
            return CategoryResource::collection($paginator);
        }

        return Inertia::render('Canteen/Categories/Index', [
            'categories' => CategoryResource::collection($paginator),
            'filters' => $request->only(['search', 'is_active']),
        ]);
    }

    public function store(StoreCategoryRequest $request)
    {
        $category = $this->service->create($request->validated());

        if ($request->wantsJson()) {
            return (new CategoryResource($category))->response()->setStatusCode(201);
        }

        return redirect()->route('canteen.categories.index')->with('success', 'Category created.');
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $category = $this->service->update($category, $request->validated());

        if ($request->wantsJson()) {
            return new CategoryResource($category);
        }

        return redirect()->route('canteen.categories.index')->with('success', 'Category updated.');
    }

    public function destroy(Request $request, Category $category)
    {
        $this->service->delete($category);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Category deleted.']);
        }

        return redirect()->route('canteen.categories.index')->with('success', 'Category deleted.');
    }
}
