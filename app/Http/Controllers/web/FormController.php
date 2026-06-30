<?php

namespace App\Http\Controllers\web;

use App\Models\Form;
use inertia\inertia;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class FormController extends Controller
{
    public function index($id)
    {
        Form::where('id', $id)->where('status', 'enable')->firstOrFail();

        $submissionId = request()->query('submission');

        return Inertia::render('Forms/RuntimeFill', [
            'formId' => (int) $id,
            'locale' => app()->getLocale() === 'en' ? 'en' : 'ar',
            'submissionId' => $submissionId ? (int) $submissionId : null,
        ]);
    }
}
