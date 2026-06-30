<?php

namespace App\Modules\Canteen\Models;

use App\Models\User;
use App\Modules\Canteen\Models\Concerns\HasStudentBlockSchedule;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentBlockedProduct extends CanteenModel
{
    use HasStudentBlockSchedule;

    protected $table = 'canteen_student_blocked_products';

    protected $fillable = [
        'student_id_ref',
        'product_id',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

}
