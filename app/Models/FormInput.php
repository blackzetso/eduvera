<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormInput extends Model
{
    protected $table = 'form_inputs';

    protected $fillable = [
        'form_id',
        'section_id',
        'sort_order',
        'name',
        'label_en',
        'type',
        'required',
        'options',
        'schema',
    ];

    protected $casts = [
        'options' => 'array',
        'schema' => 'array',
        'required' => 'boolean',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(FormSection::class, 'section_id');
    }
}
