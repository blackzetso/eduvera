<?php

namespace App\Http\Controllers;

use App\Models\LiveStream;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamWatchController extends Controller
{
    public function show(LiveStream $liveStream)
    {
        // Must have a recording to watch
        abort_if(
            empty($liveStream->video_url) && empty($liveStream->recording_path),
            404,
            'لا يوجد تسجيل لهذا البث.'
        );

        $isYoutube = false;
        $youtubeId = null;
        $isServer  = false;

        if ($liveStream->video_url) {
            $isYoutube = true;
            // Extract YouTube video ID
            preg_match(
                '/(?:youtube\.com\/(?:watch\?.*v=|embed\/|shorts\/)|youtu\.be\/)([\w\-]+)/',
                $liveStream->video_url,
                $matches
            );
            $youtubeId = $matches[1] ?? null;
        } elseif ($liveStream->recording_path) {
            $isServer = Storage::disk('local')->exists($liveStream->recording_path);
        }

        return Inertia::render('StreamWatch', [
            'stream' => [
                'id'           => $liveStream->id,
                'title'        => $liveStream->title,
                'subject'      => $liveStream->subject,
                'teacher_name' => $liveStream->teacher_name,
                'start_datetime' => $liveStream->start_datetime?->format('Y-m-d H:i'),
            ],
            'isYoutube'   => $isYoutube,
            'youtubeId'   => $youtubeId,
            'isServer'    => $isServer,
            'videoUrl'    => $isServer ? route('streams.stream-recording', $liveStream->id) : null,
        ]);
    }

    /**
     * Stream the video file inline with HTTP Range support.
     * Range requests are required by browsers to seek and display video duration.
     */
    public function stream(LiveStream $liveStream): StreamedResponse
    {
        abort_if(empty($liveStream->recording_path), 404);
        abort_unless(Storage::disk('local')->exists($liveStream->recording_path), 404);

        $path     = Storage::disk('local')->path($liveStream->recording_path);
        $fileSize = filesize($path);
        $filename = basename($liveStream->recording_path);

        // Determine MIME from extension (mime_content_type can misidentify webm)
        $ext  = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'webm' => 'video/webm',
            'mp4'  => 'video/mp4',
            'mkv'  => 'video/x-matroska',
            'ogg'  => 'video/ogg',
            default => mime_content_type($path) ?: 'video/webm',
        };

        $start  = 0;
        $end    = $fileSize - 1;
        $status = 200;

        $headers = [
            'Content-Type'           => $mime,
            'Content-Disposition'    => 'inline; filename="' . $filename . '"',
            'Accept-Ranges'          => 'bytes',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control'          => 'no-store',
        ];

        // Handle Range request
        $rangeHeader = request()->headers->get('Range');
        if ($rangeHeader && preg_match('/bytes=(\d*)-(\d*)/', $rangeHeader, $m)) {
            $start  = $m[1] !== '' ? (int) $m[1] : 0;
            $end    = $m[2] !== '' ? min((int) $m[2], $fileSize - 1) : $fileSize - 1;
            $status = 206;
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$fileSize}";
        }

        $length = $end - $start + 1;
        $headers['Content-Length'] = $length;

        return response()->stream(function () use ($path, $start, $length) {
            $fp        = fopen($path, 'rb');
            fseek($fp, $start);
            $remaining = $length;
            $chunkSize = 1024 * 64;
            while ($remaining > 0 && ! feof($fp)) {
                $toRead = min($chunkSize, $remaining);
                echo fread($fp, $toRead);
                $remaining -= $toRead;
                ob_flush();
                flush();
            }
            fclose($fp);
        }, $status, $headers);
    }

    public function download(LiveStream $liveStream): StreamedResponse
    {
        abort_if(empty($liveStream->recording_path), 404);
        abort_unless(Storage::disk('local')->exists($liveStream->recording_path), 404);

        $filename = basename($liveStream->recording_path);

        return Storage::disk('local')->download($liveStream->recording_path, $filename);
    }
}
