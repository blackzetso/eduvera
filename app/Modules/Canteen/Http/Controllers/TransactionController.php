<?php

namespace App\Modules\Canteen\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Canteen\Http\Resources\SaleResource;
use App\Modules\Canteen\Models\Sale;
use App\Modules\Canteen\Services\SaleVoidService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use InvalidArgumentException;

class TransactionController extends Controller
{
    public function __construct(protected SaleVoidService $voidService) {}

    public function index(Request $request)
    {
        $sales = Sale::query()
            ->with('items')
            ->when($request->date, fn ($q) => $q->whereDate('sold_at', $request->date))
            ->when($request->student_id_ref, fn ($q) => $q->where('student_id_ref', $request->student_id_ref))
            ->orderByDesc('sold_at')
            ->paginate(20);

        return Inertia::render('Canteen/Transactions/Index', [
            'sales' => SaleResource::collection($sales),
            'filters' => $request->only(['date', 'student_id_ref']),
        ]);
    }

    public function show(Sale $transaction)
    {
        $transaction->load(['items', 'walletReadyTransaction']);

        return Inertia::render('Canteen/Transactions/Show', [
            'sale' => new SaleResource($transaction),
        ]);
    }

    public function void(Request $request, Sale $transaction)
    {
        $request->validate(['void_reason' => ['required', 'string', 'max:500']]);

        try {
            $this->voidService->void(
                $transaction,
                $request->void_reason,
                (int) $request->user()->id,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Sale voided.');
    }
}
