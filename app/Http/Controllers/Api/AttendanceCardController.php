<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AttendanceCardScanService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceCardController extends Controller
{
    public function scan(Request $request, AttendanceCardScanService $scanner)
    {
        $validated = $request->validate([
            'card_code' => 'required|string',
            'device_id' => 'required|string',
            'scan_time' => 'required|date',
            'scan_id' => 'required|string|max:100',
        ]);

        $reader = $scanner->authenticateReader(
            $validated['device_id'],
            $request->bearerToken()
        );

        $result = $scanner->recordScan(
            $reader,
            $validated['card_code'],
            Carbon::parse($validated['scan_time']),
            $validated['scan_id'],
        );

        $statusCode = match ($result['status']) {
            'unknown_card' => 404,
            default => 200,
        };

        return response()->json($result, $statusCode);
    }
}
