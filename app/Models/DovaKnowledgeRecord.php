<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DovaKnowledgeRecord extends Model
{
    protected $fillable = [
        'source_slug',
        'record_key',
        'title',
        'content',
        'locale',
        'content_updated_at',
        'indexed_at',
    ];

    protected $casts = [
        'content_updated_at' => 'datetime',
        'indexed_at' => 'datetime',
    ];
}
