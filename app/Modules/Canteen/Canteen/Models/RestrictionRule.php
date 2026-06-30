<?php

namespace App\Modules\Canteen\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RestrictionRule extends CanteenModel
{
    use SoftDeletes;

    protected $table = 'canteen_restriction_rules';

    protected $fillable = [
        'code', 'name', 'rule_type', 'config', 'severity', 'is_active',
        'created_by', 'updated_by',
    ];

    protected $casts = ['config' => 'array', 'is_active' => 'boolean'];

    public function assignments(): HasMany
    {
        return $this->hasMany(StudentRestrictionAssignment::class, 'rule_id');
    }
}
