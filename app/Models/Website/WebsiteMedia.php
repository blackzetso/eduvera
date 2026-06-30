<?php

namespace App\Models\Website;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\Website\WebsiteMediaService;

class WebsiteMedia extends Model
{
    protected $fillable = [
        'disk', 'path', 'filename', 'alt', 'mime_type', 'size', 'uploaded_by',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): string
    {
        return '/storage/'.ltrim(str_replace('\\', '/', $this->path), '/');
    }

    public function absoluteUrl(): string
    {
        app(WebsiteMediaService::class)->mirrorToPublicWebRoot($this->path);

        return url($this->url());
    }

    public function toImageRef(?string $fallbackAlt = null): array
    {
        return [
            'assetKey' => 'media-'.$this->id,
            'src' => $this->url(),
            'alt' => $this->alt ?? $fallbackAlt ?? $this->filename,
        ];
    }
}
