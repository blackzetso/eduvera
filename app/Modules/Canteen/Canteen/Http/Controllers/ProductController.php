<?php

namespace App\Modules\Canteen\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Canteen\Http\Requests\StoreProductRequest;
use App\Modules\Canteen\Http\Requests\UpdateProductRequest;
use App\Modules\Canteen\Http\Resources\CategoryResource;
use App\Modules\Canteen\Http\Resources\ProductResource;
use App\Modules\Canteen\Models\Product;
use App\Modules\Canteen\Services\CategoryService;
use App\Modules\Canteen\Services\InventoryLedgerService;
use App\Modules\Canteen\Services\ProductService;
use App\Modules\Canteen\Support\CanteenPermission;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function __construct(
        protected ProductService $service,
        protected CategoryService $categories,
        protected InventoryLedgerService $inventory,
    ) {}

    public function index(Request $request)
    {
        $paginator = $this->service->paginate($request->only(['search', 'is_active', 'category_id', 'per_page']));
        $stock = $this->inventory->stockMap($paginator->getCollection()->pluck('id')->all());
        $paginator->getCollection()->transform(function (Product $p) use ($stock) {
            $p->setAttribute('on_hand', $stock[$p->id] ?? '0');

            return $p;
        });

        if ($request->wantsJson()) {
            return ProductResource::collection($paginator);
        }

        return Inertia::render('Canteen/Products/Index', [
            'products' => ProductResource::collection($paginator),
            'categories' => CategoryResource::collection($this->categories->allActive()),
            'filters' => $request->only(['search', 'is_active', 'category_id']),
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Canteen/Products/Form', [
            'product' => null,
            'categories' => CategoryResource::collection($this->categories->allActive()),
            ...$this->formMeta($request),
        ]);
    }

    public function edit(Request $request, Product $product)
    {
        $product->load('category');
        $product->setAttribute('on_hand', $this->inventory->onHand($product->id));

        return Inertia::render('Canteen/Products/Form', [
            'product' => new ProductResource($product),
            'categories' => CategoryResource::collection($this->categories->allActive()),
            ...$this->formMeta($request),
        ]);
    }

    public function store(StoreProductRequest $request)
    {
        $validated = $request->validated();
        $initialStock = $validated['initial_stock'] ?? null;
        unset($validated['initial_stock']);

        $product = $this->service->create($validated);

        if ($initialStock !== null && bccomp((string) $initialStock, '0', 3) > 0) {
            if (CanteenPermission::allows($request->user(), 'canteen.inventory.manage')) {
                $this->inventory->record(
                    $product->id,
                    'opening_stock',
                    (string) $initialStock,
                    isset($validated['cost_price']) ? (string) $validated['cost_price'] : null,
                    null,
                    null,
                    'رصيد افتتاحي عند إنشاء المنتج',
                );
            }
        }

        if ($request->wantsJson()) {
            $product->setAttribute('on_hand', $this->inventory->onHand($product->id));

            return (new ProductResource($product))->response()->setStatusCode(201);
        }

        return redirect()
            ->route('canteen.products.edit', $product)
            ->with('success', 'تم إنشاء المنتج.');
    }

    protected function formMeta(Request $request): array
    {
        return [
            'can_manage_inventory' => CanteenPermission::allows($request->user(), 'canteen.inventory.manage'),
            'can_view_inventory' => CanteenPermission::allows($request->user(), 'canteen.inventory.view'),
        ];
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $product = $this->service->update($product, $request->validated());

        if ($request->wantsJson()) {
            return new ProductResource($product);
        }

        return redirect()->route('canteen.products.index')->with('success', 'Product updated.');
    }

    public function destroy(Request $request, Product $product)
    {
        $this->service->delete($product);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Product deleted.']);
        }

        return redirect()->route('canteen.products.index')->with('success', 'Product deleted.');
    }
}
