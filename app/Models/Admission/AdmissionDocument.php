<?php

namespace App\Models\Admission;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdmissionDocument extends Model
{
    protected $fillable = [
        'admission_application_id',
        'document_key',
        'label',
        'required',
        'status',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'reviewed_by_user_id',
        'reviewed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'admission_application_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(AdmissionDocumentHistory::class);
    }
}
