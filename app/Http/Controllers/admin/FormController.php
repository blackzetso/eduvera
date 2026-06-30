<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormTemplate;
use App\Services\FormBuilder\FormBuilderPersistenceService;
use App\Services\Translation\BilingualAutoTranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FormController extends Controller
{
    public function __construct(
        protected FormBuilderPersistenceService $builder,
        protected BilingualAutoTranslationService $translator,
    ) {}

    public function index()
    {
        $forms = Form::orderBy('id', 'DESC')->paginate(10);

        return Inertia::render('Admin/theme1/Forms/Index', compact('forms'));
    }

    public function search($phrase, Request $request)
    {
        $forms = Form::where('name', 'like', '%'.$phrase.'%')
            ->orWhere('name_en', 'like', '%'.$phrase.'%')
            ->orderBy('id', 'DESC')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/theme1/Forms/Index', [
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

    public function create()
    {
        return Inertia::render('Admin/theme1/Forms/Create', [
            'templates' => $this->templateList(),
            'fieldTypes' => config('form-builder.field_type_groups'),
            'builderConfig' => [
                'publication_statuses' => config('form-builder.publication_statuses'),
                'visibility_audiences' => config('form-builder.visibility_audiences'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateBuilderPayload($request);

        try {
            $this->builder->store($data);

            return redirect()->route('admin.forms.index')->with('success', 'تم إنشاء النموذج بنجاح');
        } catch (\Exception $e) {
            return back()->with('error', 'حصل خطأ أثناء إنشاء النموذج: '.$e->getMessage());
        }
    }

    public function edit(string $id)
    {
        $form = Form::findOrFail($id);

        return Inertia::render('Admin/theme1/Forms/Edit', [
            'form' => $this->builder->toBuilderPayload($form),
            'formMeta' => $form->only(['id', 'status', 'publication_status']),
            'templates' => $this->templateList(),
            'fieldTypes' => config('form-builder.field_type_groups'),
            'builderConfig' => [
                'publication_statuses' => config('form-builder.publication_statuses'),
                'visibility_audiences' => config('form-builder.visibility_audiences'),
            ],
        ]);
    }

    public function update(Request $request, string $id)
    {
        $form = Form::findOrFail($id);
        $data = $this->validateBuilderPayload($request);

        try {
            $this->builder->update($form, $data);

            return redirect()->route('admin.forms.index')->with('success', 'تم تحديث النموذج بنجاح');
        } catch (\Exception $e) {
            return back()->with('error', 'حصل خطأ أثناء التحديث: '.$e->getMessage());
        }
    }

    public function destroy(string $id)
    {
        $form = Form::findOrFail($id);
        $form->delete();

        return redirect()
            ->route('admin.forms.index')
            ->with('success', 'تم حذف النموذج بنجاح');
    }

    public function translateBilingual(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payload' => 'required|array',
        ]);

        return response()->json([
            'payload' => $this->translator->translatePayload($data['payload']),
        ]);
    }

    public function templates()
    {
        return response()->json([
            'templates' => $this->templateList(),
        ]);
    }

    public function template(string $key)
    {
        $template = FormTemplate::where('key', $key)->firstOrFail();

        return response()->json([
            'template' => [
                'key' => $template->key,
                'name_ar' => $template->name_ar,
                'name_en' => $template->name_en,
                'definition' => $template->definition,
            ],
        ]);
    }

    protected function templateList(): array
    {
        return FormTemplate::orderBy('name_ar')
            ->get(['key', 'name_ar', 'name_en', 'category', 'description_ar', 'description_en'])
            ->toArray();
    }

    protected function validateBuilderPayload(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'publication_status' => 'nullable|in:draft,published,archived',
            'template_key' => 'nullable|string|max:100',
            'visibility_settings' => 'nullable|array',
            'submission_settings' => 'nullable|array',
            'workflow_definition' => 'nullable|array',
            'logic_rules' => 'nullable|array',
            'builder_settings' => 'nullable|array',
            'sections' => 'nullable|array',
            'sections.*.title_ar' => 'required_with:sections|string|max:255',
            'sections.*.title_en' => 'nullable|string|max:255',
            'sections.*.description_ar' => 'nullable|string',
            'sections.*.description_en' => 'nullable|string',
            'sections.*.fields' => 'nullable|array',
            'sections.*.fields.*.type' => 'required|string|max:50',
            'sections.*.fields.*.name' => 'nullable|string|max:255',
            'sections.*.fields.*.name_ar' => 'nullable|string|max:255',
            'sections.*.fields.*.name_en' => 'nullable|string|max:255',
            'sections.*.fields.*.label_en' => 'nullable|string|max:255',
            'sections.*.fields.*.schema' => 'nullable|array',
            'inputs' => 'nullable|array',
            'inputs.*.name' => 'required_without:sections.*.fields.*.name_ar|string|max:255',
            'inputs.*.label_en' => 'nullable|string|max:255',
            'inputs.*.type' => 'required_with:inputs|string',
        ]);

        return $data;
    }
}
