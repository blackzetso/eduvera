<?php

namespace App\Services\Website;

use App\Models\Website\WebsiteMedia;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class WebsiteMediaService
{
    public function store(UploadedFile $file, ?string $alt = null): WebsiteMedia
    {
        $path = $file->store('website/media', 'public');
        $this->mirrorToPublicWebRoot($path);

        return WebsiteMedia::query()->create([
            'disk' => 'public',
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'alt' => $alt,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => Auth::id(),
        ]);
    }

    public function delete(WebsiteMedia $media): void
    {
        if ($media->path && Storage::disk($media->disk)->exists($media->path)) {
            Storage::disk($media->disk)->delete($media->path);
        }
        $this->removePublicWebMirror($media->path);
        $media->delete();
    }

    /**
     * Laragon/Windows installs sometimes keep public/storage as a real folder instead of a symlink,
     * so new files under storage/app/public are not web-visible until mirrored here.
     */
    public function mirrorToPublicWebRoot(string $path): void
    {
        $source = storage_path('app/public/'.ltrim($path, '/'));
        if (! is_file($source)) {
            return;
        }

        $target = public_path('storage/'.ltrim($path, '/'));
        $dir = dirname($target);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (! is_file($target) || filemtime($source) > filemtime($target)) {
            copy($source, $target);
        }
    }

    public function removePublicWebMirror(?string $path): void
    {
        if (! $path) {
            return;
        }

        $target = public_path('storage/'.ltrim($path, '/'));
        if (is_file($target)) {
            @unlink($target);
        }
    }

    public function resolveImage(?WebsiteMedia $media, ?string $src, ?string $alt, ?string $assetKey = null): ?array
    {
        if ($media) {
            $this->mirrorToPublicWebRoot($media->path);
            $ref = $media->toImageRef($alt);
            $ref['src'] = $media->absoluteUrl();
            if ($assetKey) {
                $ref['assetKey'] = $assetKey;
            }

            return $ref;
        }

        if ($src) {
            $resolved = $this->resolvePublicSrc($src);

            return [
                'assetKey' => $assetKey ?? 'static',
                'src' => $resolved,
                'alt' => $alt ?? '',
            ];
        }

        return null;
    }

    public function resolvePublicSrc(string $src): string
    {
        if (str_starts_with($src, '/storage/')) {
            $path = ltrim(substr($src, strlen('/storage/')), '/');
            $this->mirrorToPublicWebRoot($path);

            return url($src);
        }

        return $src;
    }

    /**
     * @param  array{
     *   file?: string,
     *   src?: string,
     *   alt?: string,
     *   media_id_column?: string,
     *   src_column?: string,
     *   alt_column?: string,
     * }  $keys
     */
    public function applyModelImage(object $model, Request $request, array $keys = [], ?string $defaultAlt = null): void
    {
        $fileKey = $keys['file'] ?? 'image';
        $srcKey = $keys['src'] ?? 'image_src';
        $altKey = $keys['alt'] ?? 'image_alt';
        $mediaIdCol = $keys['media_id_column'] ?? 'image_media_id';
        $srcCol = $keys['src_column'] ?? 'image_src';
        $altCol = $keys['alt_column'] ?? 'image_alt';

        $alt = $request->input($altKey);

        if ($request->hasFile($fileKey)) {
            $media = $this->store($request->file($fileKey), $alt ?: $defaultAlt);
            $model->{$mediaIdCol} = $media->id;
            $model->{$srcCol} = $media->url();
            $model->{$altCol} = $alt ?: $media->alt;

            return;
        }

        $src = trim((string) $request->input($srcKey, ''));
        if ($src !== '') {
            $model->{$mediaIdCol} = null;
            $model->{$srcCol} = $this->resolvePublicSrc($src);
            if ($alt !== null) {
                $model->{$altCol} = $alt;
            }

            return;
        }

        if ($alt !== null) {
            $model->{$altCol} = $alt;
        }
    }
}
