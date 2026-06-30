<?php

namespace App\Modules\Canteen\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Canteen\Http\Resources\CategoryResource;
use App\Modules\Canteen\Services\CategoryService;
use App\Modules\Canteen\Services\InventoryLedgerService;
use App\Modules\Canteen\Services\ProductService;
use Inertia\Inertia;

class PosController extends Controller
{
    public function __construct(
        protected ProductService $products,
        protected CategoryService $categories,
        protected InventoryLedgerService $inventory,
    ) {}

    public function index()
    {
        $catalog = $this->products->listActiveCatalog();
        $stock = $this->inventory->stockMap($catalog->pluck('id')->all());
        $catalog->transform(function ($p) use ($stock) {
            $p->setAttribute('on_hand', $stock[$p->id] ?? '0');

            return $p;
        });

        return Inertia::render('Canteen/Pos/Index', [
            'categories' => CategoryResource::collection($this->categories->allActive()),
            'products' => $catalog,
        ]);
    }
}
