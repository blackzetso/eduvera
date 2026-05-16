<?php

namespace App\Http\Controllers\admin;

use Inertia\Inertia;
use App\Models\Subject;
use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class SubjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $subjects = Subject::with('categories')
            ->when($request->search, fn($q) => $q->where('name', 'like', '%' . $request->search . '%'))
            ->orderBy('id', 'DESC')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/theme1/Subjects/Index', [
            'subjects' => $subjects,
            'filters' => $request->only('search'),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $categories = Category::with('children')->whereNull('parent_id')->get();

        return Inertia::render('Admin/theme1/Subjects/Create', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
        ]);

        $subject = Subject::create([
            'name' => $data['name'],
        ]);

        // ربط المادة بالصفوف الدراسية
        if (isset($data['category_ids']) && count($data['category_ids']) > 0) {
            $subject->categories()->attach($data['category_ids']);
        }

        return redirect()->route('admin.subjects.index')
            ->with('success', 'تم إضافة المادة بنجاح');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $subject = Subject::with('categories')->findOrFail($id);

        return Inertia::render('Admin/theme1/Subjects/Show', [
            'subject' => $subject,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $subject = Subject::with('categories')->findOrFail($id);
        $categories = Category::with('children')->whereNull('parent_id')->get();

        return Inertia::render('Admin/theme1/Subjects/Edit', [
            'subject' => $subject,
            'categories' => $categories,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $subject = Subject::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
        ]);

        $subject->update([
            'name' => $data['name'],
        ]);

        // تحديث ربط المادة بالصفوف الدراسية
        if (isset($data['category_ids'])) {
            $subject->categories()->sync($data['category_ids']);
        } else {
            $subject->categories()->detach();
        }

        return redirect()->route('admin.subjects.index')
            ->with('success', 'تم تحديث المادة بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $subject = Subject::findOrFail($id);
        $subject->delete();

        return redirect()->route('admin.subjects.index')
            ->with('success', 'تم حذف المادة بنجاح');
    }

    /**
     * Search subjects.
     */
    public function search($phrase, Request $request)
    {
        $subjects = Subject::where('name', 'like', '%' . $phrase . '%')
            ->with('categories')
            ->orderBy('id', 'DESC')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/theme1/Subjects/Index', [
            'subjects' => $subjects,
            'filters' => ['search' => $phrase],
        ]);
    }
}
