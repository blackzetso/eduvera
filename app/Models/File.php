<?php

namespace App\Models;

use App\Models\Lecture;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class File extends Model
{
    protected $fillable = [
        'name',
        'url',
        'path',
        'type',
        'description',
        'video_id',
        'lecture_id',
        'access_type', // 'free' or 'premium'
        'embed_code',
    ];

    public function lecture(): BelongsTo
    {
        return $this->belongsTo(Lecture::class);
    }

    public function consumption(): HasOne
    {
        return $this->hasOne(StorageConsumption::class);
    }

    public function isBunnyStream(): bool
    {
        return $this->type === 'bunny_stream';
    }

    public function isYoutube(): bool
    {
        return $this->type === 'youtube';
    }

    public function isExternal(): bool
    {
        return $this->type === 'external';
    }
}

