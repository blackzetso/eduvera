<?php

namespace App\Http\Controllers\admin;

use inertia\inertia;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;


class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = Category::with('parent')->orderBy('id', 'DESC')->paginate(10);
        return inertia::render('Admin/theme1/Categories/Index',compact('categories'));
    }

    public function search($phrase, Request $request)
    {
        $categories = Category::where('name', 'like', '%' . $phrase . '%')
            ->orderBy('id', 'DESC')
            ->paginate(10)
            ->withQueryString();

        return inertia::render('Admin/theme1/Categories/Index', [
            'categories' => $categories,
            'filters' => ['search' => $phrase],
        ]);
    }

    public function toggleStatus($id)
    {
        $category = Category::findOrFail($id);
        $category->status = $category->status === 'enable' ? 'disable' : 'enable';
        $category->save();

        return redirect()->route('admin.categories.index')->with('success', 'تم تحديث الحالة بنجاح');
    }

    /**
     * Show the category for creating a new resource.
     */
    public function create()
    {
        $categories = Category::with('children')->whereNull('parent_id')->get();

        return Inertia::render('Admin/theme1/Categories/Create', [
            'categories' => $categories,
        ]);
    }


    public function store(Request $request)
    {
        $data = $request->validate([
            'categories'             => 'required|array|min:1',
            'categories.*.name'      => 'required|string|max:255',
            'categories.*.parent_id' => 'nullable|exists:categories,id',
        ]);

        foreach ($data['categories'] as $item) {
            Category::create([
                'name'      => $item['name'],
                'parent_id' => $item['parent_id'] ?: null,
            ]);
        }

        return redirect()->route('admin.categories.index')->with('success', 'تم إضافة الأقسام بنجاح');
    }



    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the category for editing the specified resource.
     */
    public function edit(string $id)
    {
        $category = Category::findOrFail($id);
        $categories = Category::with('children')->whereNull('parent_id')->get();

        return inertia::render('Admin/theme1/Categories/Edit', [
            'category' => $category,
            'categories' => $categories,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        // ممنوع القسم يبقى أب لنفسه
        if ($data['parent_id'] == $id) {
            return back()->withErrors(['parent_id' => 'لا يمكن اختيار نفس القسم كقسم أب.']);
        }

        $category = Category::findOrFail($id);

        $category->update([
            'name' => $data['name'],
            'parent_id' => $data['parent_id'] ?: null, // لو parent_id فاضي → null
        ]);

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'تم تعديل القسم بنجاح');
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'تم حذف النموذج بنجاح');
    }

    public function destroyAll()
    {
        Category::query()->delete();

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'تم حذف جميع الأقسام بنجاح');
    }

    /**
     * Return category with its direct children as JSON for the duplicate modal.
     */
    public function duplicateInfo(string $id)
    {
        $category = Category::with('children')->findOrFail($id);
        return response()->json($category);
    }

    /**
     * Duplicate a category (and optionally its subcategories).
     */
    public function duplicate(Request $request, string $id)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'subcategories'=> 'nullable|array',
            'subcategories.*.name' => 'required|string|max:255',
        ]);

        $original = Category::findOrFail($id);

        $newCategory = Category::create([
            'name'      => $data['name'],
            'parent_id' => $original->parent_id,
        ]);

        if (!empty($data['subcategories'])) {
            foreach ($data['subcategories'] as $sub) {
                Category::create([
                    'name'      => $sub['name'],
                    'parent_id' => $newCategory->id,
                ]);
            }
        }

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'تم تكرار القسم بنجاح');
    }
}
