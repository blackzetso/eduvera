<?php

namespace App\Modules\Canteen\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentVisibilityQueue extends Model
{
    use HasUuids;

    protected $table = 'canteen_parent_visibility_queue';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'sale_id', 'student_id_ref', 'guardian_id_ref', 'payload',
        'visibility_status', 'published_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'published_at' => 'datetime',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }
}
