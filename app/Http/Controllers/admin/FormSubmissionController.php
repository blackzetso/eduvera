<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FormSubmissionController extends Controller
{
    public function index(Request $request, Form $form)
    {
        $submissions = FormSubmission::query()
            ->where('form_id', $form->id)
            ->with('user:id,name,email')
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('status', 'like', "%{$search}%")
                        ->orWhere('workflow_stage', 'like', "%{$search}%");
                });
            })
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/theme1/Forms/Submissions/Index', [
            'form' => $form->only(['id', 'name', 'name_en', 'publication_status']),
            'submissions' => $submissions,
            'filters' => $request->only(['search', 'status']),
        ]);
    }
}
