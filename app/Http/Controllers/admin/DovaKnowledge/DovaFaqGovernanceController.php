<?php

namespace App\Http\Controllers\admin\DovaKnowledge;

use App\Http\Controllers\Controller;
use App\Services\Dova\DovaFaqGovernanceService;
use Inertia\Inertia;

class DovaFaqGovernanceController extends Controller
{
    public function index(DovaFaqGovernanceService $governance)
    {
        return Inertia::render('Admin/theme1/DovaKnowledge/Governance/Index', [
            'dashboard' => $governance->governanceDashboard(),
        ]);
    }
}
