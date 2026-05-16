<?php

namespace App\Http\Controllers\admin;

use App\Models\Form;
use inertia\inertia;
use App\Models\FormInput;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class FormController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $forms = Form::orderBy('id', 'DESC')->paginate(10);
        return inertia::render('Admin/theme1/Forms/Index',compact('forms'));
    }

    public function search($phrase, Request $request)
    {
        $forms = Form::where('name', 'like', '%' . $phrase . '%')
            ->orderBy('id', 'DESC')
            ->paginate(10)
            ->withQueryString();

        return inertia::render('Admin/theme1/Forms/Index', [
            'forms' => $forms,
            'filters' => ['search' => $phrase],
        ]);
    }

    public function toggleStatus($id)
    {
        $form = Form::findOrFail($id);
        $form->status = $form->status === 'enable' ? 'disable' : 'enable';
        $form->save();

        return redirect()->route('admin.forms.index')->with('success', 'تم تحديث الحالة بنجاح');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
         return inertia::render('Admin/theme1/Forms/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'inputs' => 'nullable|array',
            'inputs.*.name' => 'required|string|max:255',
            'inputs.*.type' => 'required|string',
            'inputs.*.required' => 'boolean',
            'inputs.*.options' => 'nullable|array',
            'inputs.*.options.*.value' => 'nullable|string',
        ]);

         DB::beginTransaction();

    try {
        // إنشاء الفورم
        $form = Form::create([
            'name' => $data['name'],
        ]);

        // إضافة الحقول
        if (!empty($data['inputs'])) {
            foreach ($data['inputs'] as $input) {
                FormInput::create([
                    'form_id' => $form->id,
                    'name' => $input['name'],
                    'type' => $input['type'],
                    'required' => $input['required'] ?? false,
                    'options' => !empty($input['options']) ? $input['options'] : null,
                ]);
            }
        }

        DB::commit();

        return redirect()->route('admin.forms.index')->with('success', 'تم إنشاء النموذج مع الحقول');

    } catch (\Exception $e) {
        DB::rollBack();
        return back()->with('error', 'حصل خطأ أثناء إنشاء النموذج: '.$e->getMessage());
    }

        return redirect()->route('admin.forms.index');
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $form = Form::findOrFail($id);
        return inertia::render('Admin/theme1/Forms/Edit',compact('form'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $form = Form::findOrFail($id);
        $form->update($data);
    // return;
        return redirect()->route('admin.forms.index');
        //return redirect()->route('admin.forms.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $form = Form::findOrFail($id);
        $form->delete();

        return redirect()
            ->route('admin.forms.index')
            ->with('success', 'تم حذف النموذج بنجاح');
        }
}
