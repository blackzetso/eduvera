<?php

namespace App\Modules\Canteen\Models;

use App\Models\User;
use App\Modules\Canteen\Models\Concerns\HasStudentBlockSchedule;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentBlockedCategory extends CanteenModel
{
    use HasStudentBlockSchedule;

    protected $table = 'canteen_student_blocked_categories';

    protected $fillable = [
        'student_id_ref',
        'category_id',
        'block_source',
        'reason',
        'notes',
        'is_active',
        'starts_at',
        'expires_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

}
