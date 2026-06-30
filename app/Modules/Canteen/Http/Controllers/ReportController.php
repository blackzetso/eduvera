<?php

namespace App\Modules\Canteen\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Canteen\Services\ReportExportService;
use App\Modules\Canteen\Services\ReportService;
use App\Modules\Canteen\Services\RestrictionsSummaryService;
use App\Modules\Canteen\Support\CanteenPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reports,
        protected ReportExportService $export,
        protected RestrictionsSummaryService $restrictionsSummary,
    ) {}

    public function index()
    {
        return Inertia::render('Canteen/Reports/Index', [
            'restrictionsSummary' => $this->restrictionsSummary->summary(),
        ]);
    }

    public function show(Request $request, string $type)
    {
        $this->assertReportType($type);

        [$from, $to, $filters] = $this->resolveContext($request);
        $report = $this->reports->resolve($type, $from, $to, $filters);

        if ($request->wantsJson()) {
            return response()->json($report);
        }

        return Inertia::render('Canteen/Reports/Show', [
            'type' => $type,
            'typeLabel' => $this->typeLabel($type),
            'report' => $report,
            'filters' => $filters,
            'filterOptions' => $this->reports->filterOptions(),
            'canExport' => CanteenPermission::allows($request->user(), 'canteen.reports.export'),
        ]);
    }

    public function print(Request $request, string $type)
    {
        $this->assertReportType($type);

        [$from, $to, $filters] = $this->resolveContext($request);
        $report = $this->reports->resolve($type, $from, $to, $filters);

        return Inertia::render('Canteen/Reports/Print', [
            'type' => $type,
            'typeLabel' => $this->typeLabel($type),
            'report' => $report,
            'filters' => $filters,
            'generatedAt' => now()->toDateTimeString(),
        ]);
    }

    public function export(Request $request, string $type)
    {
        $this->assertReportType($type);

        [$from, $to, $filters] = $this->resolveContext($request);
        $report = $this->reports->resolve($type, $from, $to, $filters);

        return $this->export->toExcel($type, $report);
    }

    protected function resolveContext(Request $request): array
    {
        [$from, $to] = $this->range($request);

        $filters = [
            'from' => $request->input('from', $from->toDateString()),
            'to' => $request->input('to', $to->toDateString()),
            'status' => $request->input('status'),
            'cashier_user_id' => $request->input('cashier_user_id'),
            'category_id' => $request->input('category_id'),
            'stock_status' => $request->input('stock_status'),
            'student_id_ref' => $request->input('student_id_ref'),
            'grade' => $request->input('grade'),
        ];

        return [$from, $to, array_filter($filters, fn ($v) => $v !== null && $v !== '')];
    }

    protected function range(Request $request): array
    {
        if ($request->filled('from') && $request->filled('to')) {
            return [
                Carbon::parse($request->input('from'))->startOfDay(),
                Carbon::parse($request->input('to'))->endOfDay(),
            ];
        }

        return [now()->startOfMonth(), now()->endOfMonth()];
    }

    protected function assertReportType(string $type): void
    {
        if (! in_array($type, ['sales', 'products', 'inventory', 'students', 'categories'], true)) {
            abort(404);
        }
    }

    protected function typeLabel(string $type): string
    {
        return match ($type) {
            'sales' => 'Sales Report',
            'products' => 'Product Sales Report',
            'inventory' => 'Inventory Report',
            'students' => 'Student Spending Report',
            'categories' => 'Category Report',
            default => $type,
        };
    }
}
