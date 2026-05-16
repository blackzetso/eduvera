<?php

namespace App\Http\Controllers\admin;

use App\Models\File;
use App\Services\BunnyService;
use App\Services\WalletService;
use App\Services\PricingService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use ToshY\BunnyNet\Model\Api\Stream\ManageVideos\CreateVideo as StreamCreateVideo;
use ToshY\BunnyNet\Model\Api\Stream\ManageVideos\UploadVideo as StreamUploadVideo;
use Illuminate\Support\Facades\Log;
use RealRashid\SweetAlert\Facades\Alert;

class FileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //dd('file Controller');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    public function uploadToBunny(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'file' => 'required|file|mimes:mp4,mov,avi,wmv|max:2048000', // 2GB max
            'lecture_id' => 'required|exists:lectures,id',
            'description' => 'nullable|string',
            'type' => 'nullable|in:free,premium',
        ]);

        $walletService = app(WalletService::class);

        // Check if wallet is activated
        if ($walletService->needsActivation()) {
            return redirect()->back()->with([
                'needs_activation' => true,
                'error' => 'تفعيل المحفظة مطلوب لرفع الفيديوهات'
            ]);
        }

        // التحقق من حد أدنى للرصيد
        $minimumBalance = 10.00;
        $currentBalance = $walletService->getBalance();

        if ($currentBalance < $minimumBalance) {
            $balanceFormatted = rtrim(rtrim(number_format($currentBalance, 4, '.', ''), '0'), '.');
            return back()->with('error', "يجب أن يكون رصيدك \$10 على الأقل لرفع الفيديوهات. الرصيد الحالي: \${$balanceFormatted}");
        }

        try {
            $uploadedFile = $request->file('file');
            $fileSizeMB = $uploadedFile->getSize() / (1024 * 1024);

            // Use Bunny service
            $bunnyService = new BunnyService();

            // 1) Create a video entry in the library to get the video GUID
            // Using direct cURL to avoid PHP-FPM stream issues
            $libraryId = $bunnyService->getLibraryId();
            $apiKey = $bunnyService->getApiKey();

            $ch = curl_init("https://video.bunnycdn.com/library/{$libraryId}/videos");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'AccessKey: ' . $apiKey,
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'title' => $request->name,
                ]),
            ]);

            $rawBody = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            Log::info('Bunny CreateVideo cURL', [
                'httpCode' => $httpCode,
                'rawBody' => $rawBody,
                'curlError' => $curlError,
            ]);

            $createData = json_decode($rawBody, true);
            $videoGuid = $createData['guid'] ?? $createData['id'] ?? null;

            if (! $videoGuid) {
                Log::error('Bunny CreateVideo returned no guid', [
                    'httpCode' => $httpCode,
                    'curlError' => $curlError,
                    'body' => $createData ?? $rawBody,
                ]);

                // Return a safe error to client (don't leak headers/secrets)
                return response()->json([
                    'message' => 'فشل في إنشاء مدخل الفيديو (no guid returned)',
                    'bunny_create_status' => $httpCode,
                    'bunny_create_body' => is_array($createData) ? $createData : (string) $rawBody,
                ], 500);
            }

            // 2) Upload the video binary/content to the created video
            $stream = fopen($uploadedFile->getPathname(), 'r');

            $uploadResp = $bunnyService->client()->request(
                new StreamUploadVideo(
                    libraryId: (int) config('services.bunny.stream_library_id'),
                    videoId: $videoGuid,
                    body: $stream
                )
            );

            if (in_array($uploadResp->getStatusCode(), [200, 201, 204])) {
                // Create file record
                $fileRecord = File::create([
                    'name' => $request->name,
                    'type' => 'bunny_stream',
                    'path' => config('services.bunny.stream_hostname') . '/' . $videoGuid,
                    'video_id' => $videoGuid,
                    'lecture_id' => $request->lecture_id,
                    'description' => $request->description,
                    'access_type' => $request->type ?? 'free',
                ]);

                // Create consumption record (سيتم تحديثه في المزامنة)
                $fileRecord->consumption()->create([
                    'storage_gb' => $fileSizeMB / 1024,
                    'bandwidth_gb' => 0,
                    'bunny_cost' => 0,
                    'platform_cost' => 0,
                ]);

                // Return success
                $message = "تم رفع الفيديو بنجاح! سيتم حساب التكلفة في المزامنة اليومية.";
                return back()->with('success', $message);
            }

            return response()->json(['message' => 'فشل في رفع الفيديو (upload request failed)'], 500);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'حدث خطأ أثناء رفع الفيديو: ' . $e->getMessage()
            ], 500);
        }
    }
    // END uploadToBunny

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        dd($request->all());
    }

    /**
     * Display the specified resource.
     */
    public function show(File $file)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(File $file)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, File $file)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'lecture_id' => 'required|exists:lectures,id',
        ]);

        $file->update([
            'name' => $data['name'],
            'lecture_id' => $data['lecture_id'],
        ]);

        return back()->with('success', 'تم تعديل الملف بنجاح');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(File $file)
    {
        try {
            // If file is a Bunny Stream video, delete it from Bunny first
            if (($file->type ?? null) === 'bunny_stream' && ! empty($file->video_id)) {
                $libraryId = config('services.bunny.stream_library_id');
                $apiKey = config('services.bunny.stream_api_key');

                if ($libraryId && $apiKey) {
                    $ch = curl_init("https://video.bunnycdn.com/library/{$libraryId}/videos/{$file->video_id}");
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_CUSTOMREQUEST => 'DELETE',
                        CURLOPT_HTTPHEADER => [
                            'AccessKey: ' . $apiKey,
                            'Accept: application/json',
                        ],
                    ]);

                    $rawBody = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlError = curl_error($ch);
                    curl_close($ch);

                    Log::info('Bunny DeleteVideo cURL', [
                        'httpCode' => $httpCode,
                        'rawBody' => $rawBody,
                        'curlError' => $curlError,
                        'videoId' => $file->video_id,
                    ]);

                    // Accept 200/204 as success. 404 means already gone - treat as success.
                    if (! in_array($httpCode, [200, 204, 404])) {
                        return response()->json([
                            'message' => 'فشل حذف الفيديو من Bunny: HTTP ' . $httpCode,
                        ], 500);
                    }
                } else {
                    Log::warning('Bunny delete skipped: missing libraryId or apiKey');
                }
            }

            $file->delete();
            return back()->with('success', 'تم حذف الملف بنجاح (وشمل حذف الفيديو من Bunny إن وجد)');
        } catch (\Throwable $e) {
            Log::error('File delete failed', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'حدث خطأ أثناء حذف الملف: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save YouTube or Vimeo link
     */
    public function saveYoutubeLink(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'youtube' => 'required|url',
            'lecture_id' => 'required|exists:lectures,id',
            'description' => 'nullable|string',
            'type' => 'nullable|in:free,premium',
        ]);

        try {
            $url = $request->youtube;
            $videoId = $this->extractVideoId($url);

            if (!$videoId) {
                return back()->with('error', 'رابط غير صحيح. تأكد من أنه رابط YouTube أو Vimeo صحيح');
            }

            // Determine platform and create embed code
            if (str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be')) {
                $embedCode = "<iframe width=\"100%\" height=\"500\" src=\"https://www.youtube.com/embed/{$videoId}\" frameborder=\"0\" allow=\"accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture\" allowfullscreen></iframe>";
            } elseif (str_contains($url, 'vimeo.com')) {
                $embedCode = "<iframe src=\"https://player.vimeo.com/video/{$videoId}\" width=\"100%\" height=\"500\" frameborder=\"0\" allow=\"autoplay; fullscreen; picture-in-picture\" allowfullscreen></iframe>";
            } else {
                $embedCode = null;
            }

            File::create([
                'name' => $request->name,
                'type' => 'youtube',
                'url' => $url,
                'embed_code' => $embedCode,
                'lecture_id' => $request->lecture_id,
                'description' => $request->description,
                'access_type' => $request->type ?? 'free',
            ]);

            return back()->with('success', 'تم حفظ رابط الفيديو بنجاح');
        } catch (\Exception $e) {
            Log::error('Error saving YouTube link: ' . $e->getMessage());
            return back()->with('error', 'حدث خطأ أثناء حفظ الرابط');
        }
    }

    /**
     * Save external link (for any file type)
     */
    public function saveExternalLink(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'external_link' => 'required|url',
            'lecture_id' => 'required|exists:lectures,id',
            'description' => 'nullable|string',
            'type' => 'nullable|in:free,premium',
        ]);

        try {
            $url = $request->external_link;
            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);

            // Create appropriate embed code based on file type
            $embedCode = $this->createEmbedCode($url, $extension);

            File::create([
                'name' => $request->name,
                'type' => 'external',
                'url' => $url,
                'embed_code' => $embedCode,
                'lecture_id' => $request->lecture_id,
                'description' => $request->description,
                'access_type' => $request->type ?? 'free',
            ]);

            return back()->with('success', 'تم حفظ الرابط الخارجي بنجاح');
        } catch (\Exception $e) {
            Log::error('Error saving external link: ' . $e->getMessage());
            return back()->with('error', 'حدث خطأ أثناء حفظ الرابط');
        }
    }

    /**
     * Activate storage wallet
     */
    public function activateWallet()
    {
        try {
            $walletService = app(WalletService::class);
            $wallet = $walletService->activateWallet();

            return back()->with('success', "تم تفعيل المحفظة بنجاح! تم إضافة \$20 كرصيد مجاني.");
        } catch (\Exception $e) {
            Log::error('Error activating wallet: ' . $e->getMessage());
            return back()->with('error', 'حدث خطأ أثناء تفعيل المحفظة');
        }
    }

    /**
     * Extract video ID from YouTube/Vimeo URL
     */
    private function extractVideoId(string $url): ?string
    {
        // YouTube patterns
        if (preg_match('/youtube\.com\/watch\?v=([^&]+)/', $url, $matches)) {
            return $matches[1];
        }
        if (preg_match('/youtu\.be\/([^?]+)/', $url, $matches)) {
            return $matches[1];
        }
        if (preg_match('/youtube\.com\/embed\/([^?]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Vimeo patterns
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Create embed code based on file type
     */
    private function createEmbedCode(string $url, string $extension): string
    {
        $extension = strtolower($extension);

        // Video files
        if (in_array($extension, ['mp4', 'webm', 'ogg', 'mov'])) {
            return "<video width=\"100%\" height=\"500\" controls><source src=\"{$url}\" type=\"video/{$extension}\">Your browser does not support the video tag.</video>";
        }

        // PDF files
        if ($extension === 'pdf') {
            return "<iframe src=\"{$url}\" width=\"100%\" height=\"600\" frameborder=\"0\"></iframe>";
        }

        // PowerPoint files (using Office Online Viewer or Google Docs Viewer)
        if (in_array($extension, ['ppt', 'pptx'])) {
            $encodedUrl = urlencode($url);
            return "<iframe src=\"https://view.officeapps.live.com/op/embed.aspx?src={$encodedUrl}\" width=\"100%\" height=\"600\" frameborder=\"0\"></iframe>";
        }

        // Word documents
        if (in_array($extension, ['doc', 'docx'])) {
            $encodedUrl = urlencode($url);
            return "<iframe src=\"https://view.officeapps.live.com/op/embed.aspx?src={$encodedUrl}\" width=\"100%\" height=\"600\" frameborder=\"0\"></iframe>";
        }

        // Excel files
        if (in_array($extension, ['xls', 'xlsx'])) {
            $encodedUrl = urlencode($url);
            return "<iframe src=\"https://view.officeapps.live.com/op/embed.aspx?src={$encodedUrl}\" width=\"100%\" height=\"600\" frameborder=\"0\"></iframe>";
        }

        // Default: generic iframe
        return "<iframe src=\"{$url}\" width=\"100%\" height=\"600\" frameborder=\"0\" allowfullscreen></iframe>";
    }
}
