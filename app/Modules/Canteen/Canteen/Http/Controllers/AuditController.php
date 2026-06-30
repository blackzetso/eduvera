<?php

namespace App\Modules\Canteen\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Canteen\Services\AuditLogQueryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditController extends Controller
{
    public function __construct(protected AuditLogQueryService $auditLogs) {}

    public function index(Request $request)
    {
        $filters = [
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'actor_user_id' => $request->input('actor_user_id'),
            'action' => $request->input('action'),
            'subject_type' => $request->input('subject_type'),
            'search' => $request->input('search'),
        ];

        return Inertia::render('Canteen/Audit/Index', [
            'summary' => $this->auditLogs->summary(),
            'logs' => $this->auditLogs->paginate(array_filter($filters, fn ($v) => $v !== null && $v !== '')),
            'filters' => array_filter($filters, fn ($v) => $v !== null && $v !== ''),
            'filterOptions' => $this->auditLogs->filterOptions(),
        ]);
    }

    public function show(string $audit)
    {
        $log = $this->auditLogs->find($audit);

        if (! $log) {
            abort(404);
        }

        return Inertia::render('Canteen/Audit/Show', [
            'log' => $log,
        ]);
    }
}
