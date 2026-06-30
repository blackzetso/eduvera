<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Form extends Model
{
    protected $fillable = [
        'name',
        'name_en',
        'description_ar',
        'description_en',
        'status',
        'publication_status',
        'version',
        'template_key',
        'visibility_settings',
        'submission_settings',
        'workflow_definition',
        'logic_rules',
        'builder_settings',
    ];

    protected $casts = [
        'visibility_settings' => 'array',
        'submission_settings' => 'array',
        'workflow_definition' => 'array',
        'logic_rules' => 'array',
        'builder_settings' => 'array',
    ];

    public function inputs(): HasMany
    {
        return $this->hasMany(FormInput::class)->orderBy('sort_order');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(FormSection::class)->orderBy('sort_order');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }
}
