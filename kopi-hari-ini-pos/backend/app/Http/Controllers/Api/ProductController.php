<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function __construct(private StockService $stock) {}

    /**
     * GET /api/categories
     */
    public function categories(): JsonResponse
    {
        $categories = Category::orderBy('sort_order')->orderBy('name')->get();
        return response()->json(['data' => $categories]);
    }

    /**
     * GET /api/products
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with('category');

        if ($search = $request->query('search')) {
            $query->where(fn($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%"));
        }

        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($status = $request->query('status')) {
            match ($status) {
                'habis'   => $query->where('stock_qty', '<=', 0),
                'menipis' => $query->whereRaw('stock_qty > 0 AND stock_qty <= low_stock_threshold'),
                'aman'    => $query->whereRaw('stock_qty > low_stock_threshold'),
                default   => null,
            };
        }

        $paginated = $query->orderBy('name')->paginate(20);

        return response()->json([
            'data' => $paginated->map(fn($p) => $this->formatProduct($p)),
            'meta' => [
                'total'        => $paginated->total(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
            ],
        ]);
    }

    /**
     * GET /api/products/pos
     * Kasir-safe product list — NO current_cost or margin exposed.
     */
    public function posProducts(): JsonResponse
    {
        $products = Product::with('category')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn($p) => [
                'id'                  => $p->id,
                'sku'                 => $p->sku,
                'name'                => $p->name,
                'unit'                => $p->unit,
                'category_id'         => $p->category_id,
                'category_name'       => $p->category?->name,
                'selling_price'       => $p->selling_price,
                'stock_qty'           => $p->stock_qty,
                'low_stock_threshold' => $p->low_stock_threshold,
                'is_active'           => $p->is_active ? 1 : 0,
                'stock_status'        => $p->getStockStatus(),
            ]);

        return response()->json(['data' => $products]);
    }

    /**
     * POST /api/products
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id'        => 'required|exists:categories,id',
            'sku'                => ['required', 'string', 'max:30', 'unique:products',
                                    'regex:/^[A-Z0-9\-]+$/'],
            'name'               => 'required|string|max:120',
            'unit'               => 'required|string|max:20',
            'selling_price'      => 'required|integer|min:1',
            'low_stock_threshold'=> 'sometimes|numeric|min:0',
            'initial_stock'      => 'sometimes|numeric|min:0',
            'initial_cost'       => 'sometimes|integer|min:0',
        ]);

        // Validate selling_price > initial_cost
        $initialCost = $data['initial_cost'] ?? 0;
        if ($data['selling_price'] <= $initialCost && $initialCost > 0) {
            return response()->json([
                'message' => 'Harga jual harus lebih besar dari HPP.',
                'errors'  => ['selling_price' => ['Harga jual harus lebih besar dari HPP.']],
            ], 422);
        }

        $product = DB::transaction(function () use ($data, $initialCost, $request) {
            $product = Product::create([
                'category_id'        => $data['category_id'],
                'sku'                => $data['sku'],
                'name'               => $data['name'],
                'unit'               => $data['unit'],
                'selling_price'      => $data['selling_price'],
                'low_stock_threshold'=> $data['low_stock_threshold'] ?? 10,
            ]);

            // Set initial cost via direct query (bypassing guarded)
            Product::where('id', $product->id)->update(['current_cost' => $initialCost]);

            // If initial stock is provided, record as restock
            if (!empty($data['initial_stock']) && $data['initial_stock'] > 0 && $initialCost > 0) {
                $product->refresh();
                $this->stock->recordRestock(
                    $product,
                    (float) $data['initial_stock'],
                    $initialCost,
                    null,
                    'Stok awal produk baru',
                    $request->user()->id
                );
            }

            return $product->fresh();
        });

        return response()->json(['data' => $this->formatProduct($product->load('category'))], 201);
    }

    /**
     * PUT /api/products/{id}
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'category_id'        => 'sometimes|exists:categories,id',
            'name'               => 'sometimes|string|max:120',
            'unit'               => 'sometimes|string|max:20',
            'selling_price'      => 'sometimes|integer|min:1',
            'low_stock_threshold'=> 'sometimes|numeric|min:0',
            'is_active'          => 'sometimes|boolean',
        ]);

        // Validate selling_price > current_cost
        if (isset($data['selling_price'])) {
            if ($data['selling_price'] <= $product->current_cost) {
                return response()->json([
                    'message' => 'Harga jual harus lebih besar dari HPP.',
                    'errors'  => ['selling_price' => ['Harga jual harus lebih besar dari HPP.']],
                ], 422);
            }
        }

        $product->update($data);

        return response()->json(['data' => $this->formatProduct($product->load('category'))]);
    }

    /**
     * DELETE /api/products/{id} — soft delete (set is_active = false)
     */
    public function destroy(Product $product): JsonResponse
    {
        Product::where('id', $product->id)->update(['is_active' => false]);
        return response()->json(['message' => 'Produk berhasil dinonaktifkan.']);
    }

    /**
     * GET /api/products/{id}/summary
     */
    public function summary(Product $product): JsonResponse
    {
        $product->load('category');
        $movements = $product->stockMovements()->with('user')->limit(10)->get();

        return response()->json([
            'data' => [
                'product'         => $this->formatProduct($product),
                'current_cost'    => $product->current_cost,
                'margin_percent'  => $product->getMarginPercent(),
                'stock_status'    => $product->getStockStatus(),
                'recent_movements'=> $movements->map(fn($m) => [
                    'type'       => $m->type,
                    'qty_change' => $m->qty_change,
                    'qty_after'  => $m->qty_after,
                    'occurred_at'=> $m->occurred_at,
                    'user_name'  => $m->user?->name,
                ]),
            ],
        ]);
    }

    /**
     * POST /api/products/import (CSV)
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:2048']);

        $file    = $request->file('file');
        $handle  = fopen($file->getRealPath(), 'r');
        $headers = fgetcsv($handle);
        $results = ['imported' => 0, 'failed' => [], 'skipped' => 0];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 7) {
                continue;
            }
            [$sku, $name, $categoryName, $unit, $sellingPrice, $initialStock, $initialCost] = array_map('trim', $row);

            $category = Category::firstOrCreate(
                ['name' => $categoryName],
                ['sort_order' => 0]
            );

            $sellingPrice = (int) str_replace(['.', ',', 'Rp', ' '], '', $sellingPrice);
            $initialCost  = (int) str_replace(['.', ',', 'Rp', ' '], '', $initialCost);
            $initialStock = (float) $initialStock;

            if (Product::where('sku', $sku)->exists()) {
                $results['skipped']++;
                continue;
            }

            try {
                DB::transaction(function () use (
                    $sku, $name, $category, $unit, $sellingPrice, $initialStock, $initialCost, $request
                ) {
                    $product = Product::create([
                        'category_id'  => $category->id,
                        'sku'          => strtoupper($sku),
                        'name'         => $name,
                        'unit'         => $unit,
                        'selling_price'=> $sellingPrice,
                    ]);
                    Product::where('id', $product->id)->update(['current_cost' => $initialCost]);

                    if ($initialStock > 0 && $initialCost > 0) {
                        $product->refresh();
                        $this->stock->recordRestock(
                            $product, $initialStock, $initialCost,
                            null, 'Impor CSV', $request->user()->id
                        );
                    }
                });
                $results['imported']++;
            } catch (\Throwable $e) {
                $results['failed'][] = ['sku' => $sku, 'reason' => $e->getMessage()];
            }
        }

        fclose($handle);
        return response()->json(['data' => $results]);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function formatProduct(Product $product): array
    {
        return [
            'id'                  => $product->id,
            'category_id'         => $product->category_id,
            'category_name'       => $product->category?->name,
            'sku'                 => $product->sku,
            'name'                => $product->name,
            'unit'                => $product->unit,
            'selling_price'       => $product->selling_price,
            'current_cost'        => $product->current_cost,
            'margin_percent'      => $product->getMarginPercent(),
            'stock_qty'           => $product->stock_qty,
            'low_stock_threshold' => $product->low_stock_threshold,
            'stock_status'        => $product->getStockStatus(),
            'is_active'           => $product->is_active,
            'created_at'          => $product->created_at,
        ];
    }
}
