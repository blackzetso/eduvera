<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveStreamComment extends Model
{
    protected $fillable = [
        'live_stream_id',
        'parent_id',
        'author_name',
        'author_email',
        'body',
    ];

    public function liveStream(): BelongsTo
    {
        return $this->belongsTo(LiveStream::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(LiveStreamComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(LiveStreamComment::class, 'parent_id')->oldest();
    }
}
