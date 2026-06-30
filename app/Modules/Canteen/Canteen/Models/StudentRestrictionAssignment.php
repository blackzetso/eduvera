<?php

namespace App\Modules\Canteen\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentRestrictionAssignment extends CanteenModel
{
    use SoftDeletes;

    protected $table = 'canteen_student_restriction_assignments';

    protected $fillable = [
        'student_id_ref', 'rule_id', 'effective_from', 'effective_to',
        'assigned_by', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(RestrictionRule::class, 'rule_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
