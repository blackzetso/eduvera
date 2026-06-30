<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DovaKnowledgeSource extends Model
{
    protected $fillable = [
        'slug',
        'name_en',
        'name_ar',
        'enabled',
        'record_count',
        'status',
        'last_synced_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function isIndexed(): bool
    {
        return $this->status === 'indexed' && $this->record_count > 0;
    }
}
