<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveStreamReview extends Model
{
    protected $fillable = [
        'live_stream_id',
        'reviewer_name',
        'reviewer_email',
        'rating',
        'body',
    ];

    public function liveStream(): BelongsTo
    {
        return $this->belongsTo(LiveStream::class);
    }
}
