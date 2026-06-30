<?php

namespace App\Modules\Canteen\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Canteen\Http\Requests\InventoryAdjustmentRequest;
use App\Modules\Canteen\Http\Resources\ProductResource;
use App\Modules\Canteen\Models\Product;
use App\Modules\Canteen\Services\InventoryLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class InventoryController extends Controller
{
    public function __construct(protected InventoryLedgerService $inventory) {}

    public function index(Request $request)
    {
        $overview = $this->inventory->overview($request->only(['search', 'per_page']));

        return Inertia::render('Canteen/Inventory/Index', [
            'products' => ProductResource::collection($overview),
            'filters' => $request->only(['search']),
        ]);
    }

    public function ledger(Request $request, Product $product)
    {
        $ledger = $this->inventory->ledger($product->id, $request->only(['per_page']));

        if ($request->wantsJson()) {
            return response()->json($ledger);
        }

        return Inertia::render('Canteen/Inventory/Ledger', [
            'product' => new ProductResource($product->load('category')),
            'ledger' => $ledger,
            'on_hand' => $this->inventory->onHand($product->id),
        ]);
    }

    public function adjust(InventoryAdjustmentRequest $request)
    {
        $data = $request->validated();
        $tx = $this->inventory->record(
            $data['product_id'],
            $data['type'],
            (string) $data['quantity_delta'],
            isset($data['unit_cost']) ? (string) $data['unit_cost'] : null,
            null,
            null,
            $data['notes'] ?? null,
            isset($data['occurred_at']) ? Carbon::parse($data['occurred_at']) : null,
        );

        if ($request->wantsJson()) {
            return response()->json($tx, 201);
        }

        return back()->with('success', 'Inventory updated.');
    }
}
