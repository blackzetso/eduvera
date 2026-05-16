<?php

namespace App\Models;

use App\Models\Form;
use Illuminate\Database\Eloquent\Model;

class FormInput extends Model
{
    protected $table = 'form_inputs';

    protected $fillable = [
        'form_id',
        'name',
        'type',
        'required',
        'options',
    ];

    protected $casts = [
        'options' => 'array',
    ];

    public function form()
    {
        return $this->belongsTo(Form::class);
    }
}
