<?php

namespace App\Models\Admission;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionContact extends Model
{
    protected $fillable = [
        'admission_application_id',
        'matched_guardian_id',
        'name',
        'email',
        'phone',
        'national_id',
        'address',
        'communication_preferences',
        'relationship_type',
        'is_primary',
        'is_emergency_contact',
        'is_pickup_authorized',
        'is_financial_responsible',
    ];

    protected function casts(): array
    {
        return [
            'communication_preferences' => 'array',
            'is_primary' => 'boolean',
            'is_emergency_contact' => 'boolean',
            'is_pickup_authorized' => 'boolean',
            'is_financial_responsible' => 'boolean',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'admission_application_id');
    }

    public function matchedGuardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_guardian_id');
    }
}
