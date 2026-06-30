<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Services\Admission\AdmissionDocumentDefinitionService;
use App\Services\Website\WebsiteContentService;
use App\Support\Admin\PermissionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdmissionDocumentSettingsController extends Controller
{
    public function __construct(
        protected AdmissionDocumentDefinitionService $definitions,
        protected WebsiteContentService $cms,
        protected PermissionService $permissions,
    ) {}

    public function index(Request $request)
    {
        abort_unless($this->permissions->can($request->user(), 'admissions.manage'), 403);

        return Inertia::render('Admin/theme1/Admissions/Settings/DocumentDefinitions', [
            'definitions' => $this->definitions->forAdminSettings(),
            'sourceTypes' => config('admissions.document_definition_sources', []),
        ]);
    }

    public function update(Request $request)
    {
        abort_unless($this->permissions->can($request->user(), 'admissions.manage'), 403);

        $data = $request->validate([
            'definitions' => 'required|array',
            'definitions.*.id' => 'nullable|integer|exists:admission_document_definitions,id',
            'definitions.*.key' => 'nullable|string|max:80',
            'definitions.*.label_ar' => 'required|string|max:255',
            'definitions.*.label_en' => 'nullable|string|max:255',
            'definitions.*.required' => 'boolean',
            'definitions.*.enabled' => 'boolean',
            'definitions.*.sort_order' => 'integer|min:0',
        ]);

        $this->definitions->syncFromAdminInput($data['definitions']);
        $this->cms->clearCache();

        return back()->with('success', 'تم حفظ متطلبات مستندات القبول.');
    }
}
