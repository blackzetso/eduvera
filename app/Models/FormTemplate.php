<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormTemplate extends Model
{
    protected $fillable = [
        'key',
        'name_ar',
        'name_en',
        'category',
        'description_ar',
        'description_en',
        'definition',
        'is_system',
    ];

    protected $casts = [
        'definition' => 'array',
        'is_system' => 'boolean',
    ];
}
