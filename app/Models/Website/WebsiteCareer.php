<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;

class WebsiteCareer extends Model
{
    protected $fillable = [
        'title', 'department', 'type', 'description', 'apply_url', 'is_active', 'sort_order',
    ];

    protected $casts = ['is_active' => 'boolean'];
}
