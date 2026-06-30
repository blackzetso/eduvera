<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteLandingSectionRevision extends Model
{
    protected $fillable = [
        'page_id',
        'version',
        'status',
        'snapshot',
        'note',
        'created_by',
    ];

    protected $casts = [
        'snapshot' => 'array',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(WebsiteLandingPage::class, 'page_id');
    }
}
