<?php

namespace App\Http\Controllers\admin;

use App\Models\Timetable;
use App\Services\DepartmentPlanService;
use Inertia\inertia;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $departmentNeedsSummary = [];
        $timetable = Timetable::query()->where('status', 'active')->first() ?? Timetable::query()->first();
        if ($timetable) {
            $plans = app(DepartmentPlanService::class)->activePlansForTimetable($timetable);
            $departmentNeedsSummary = app(DepartmentPlanService::class)->executiveSummary($plans);
        }

        return inertia::render('Admin/theme1/Index', [
            'departmentNeedsSummary' => $departmentNeedsSummary,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
