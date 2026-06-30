<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormSection extends Model
{
    protected $fillable = [
        'form_id',
        'title_ar',
        'title_en',
        'description_ar',
        'description_en',
        'sort_order',
        'is_collapsed',
    ];

    protected $casts = [
        'is_collapsed' => 'boolean',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function inputs(): HasMany
    {
        return $this->hasMany(FormInput::class, 'section_id')->orderBy('sort_order');
    }
}
