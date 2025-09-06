<?php

namespace App\Http\Controllers\admin;

use App\Models\FormBuilder;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Validator;

class FormbuilderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $forms = FormBuilder::paginate(10);
        confirmDelete('Delete', 'Are you sure you want to delete this item ?');
        return view('admin.theme1.form-bulider.index',compact('forms'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $validator = Validator::make($request->all(),[
            'name'      => 'required|min:2|max:150|string',
        ]);

        if($validator->fails()){
            Alert::warning('Warning',$validator->messages()->all()[0]);
            return view('sweetalert::alert');
        } else {
            FormBuilder::create([
                'name' => $request->name,
            ]);
            Alert::success('Success Title', 'Success Message');
            // $forms = FormBuilder::orderBy('id','desc')->paginate(5);
            // return view('admin.theme1.form-bulider.partials.list',compact('forms'));
            return redirect()->route('admin.forms.index');
        }

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
        $form = FormBuilder::FindOrFail($id);
        return view('admin.theme1.form-bulider.edit',compact('form'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|min:2|max:150|string',
        ]);

        if ($validator->fails()) {
            Alert::warning('Warning', $validator->messages()->all()[0]);
            return back()->withErrors($validator)->withInput();
        } else {
            $form = FormBuilder::findOrFail($id); // Find the existing form
            $form->update([
                'name' => $request->name,
            ]);

            Alert::success('Success', 'Form updated successfully');
            return redirect()->route('admin.forms.index');
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        FormBuilder::where('id',$id)->delete();
        Alert::success('Success','Data Deleted Successfully');
        return redirect()->back();
    }
}
