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
        $form = Form::with('inputs')->where('id', $id)->where('status', 'enable')->firstOrFail();
        return Inertia::render('Student/Theme1/Form',compact('form'));
    }
}
