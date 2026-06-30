<?php

namespace App\Http\Controllers\admin\DovaKnowledge;

use App\Http\Controllers\Controller;
use App\Services\Dova\DovaAiAnalyticsService;
use Inertia\Inertia;

class DovaAiController extends Controller
{
    public function index(DovaAiAnalyticsService $analytics)
    {
        return Inertia::render('Admin/theme1/DovaKnowledge/AiUsage/Index', [
            'stats' => $analytics->dashboard(),
        ]);
    }
}
