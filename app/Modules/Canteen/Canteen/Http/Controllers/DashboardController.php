<?php

namespace App\Modules\Canteen\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Canteen\Services\DashboardService;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(protected DashboardService $dashboard) {}

    public function index()
    {
        return Inertia::render('Canteen/Dashboard', [
            'kpis' => $this->dashboard->kpis(),
        ]);
    }
}
