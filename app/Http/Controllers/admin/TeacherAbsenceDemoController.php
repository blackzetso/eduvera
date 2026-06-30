<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Services\TeacherAbsenceDemoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherAbsenceDemoController extends Controller
{
    public function store(Request $request, TeacherAbsenceDemoService $demoService): JsonResponse
    {
        $date = $request->input('date');
        $result = $demoService->seedForToday($date);

        $status = match (true) {
            ($result['already_exists'] ?? false) => 200,
            ($result['success'] ?? false) => 200,
            default => 422,
        };

        return response()->json($result, $status);
    }
}
