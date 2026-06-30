<?php

namespace App\Models\Admission;

use Illuminate\Database\Eloquent\Model;

class AdmissionDocumentDefinition extends Model
{
    public const SOURCE_SETTINGS = 'settings';

    public const SOURCE_FORM_BUILDER = 'form_builder';

    protected $fillable = [
        'key',
        'label_ar',
        'label_en',
        'required',
        'enabled',
        'sort_order',
        'source_type',
        'source_ref',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
