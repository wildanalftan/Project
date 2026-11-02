<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Restock;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RestockController extends Controller
{
    public function __construct(private StockService $stock) {}

    /**
     * GET /api/restocks
     */
    public function index(Request $request): JsonResponse
    {
        $restocks = Restock::with(['product', 'user'])
            ->orderByDesc('received_at')
            ->paginate(20);

        return response()->json([
            'data' => $restocks->map(fn($r) => $this->formatRestock($r)),
            'meta' => [
                'total'        => $restocks->total(),
                'current_page' => $restocks->currentPage(),
                'last_page'    => $restocks->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/restocks/preview — calculate new HPP without persisting
     */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'qty'        => 'required|numeric|min:0.001|decimal:0,3',
            'unit_cost'  => 'required|integer|min:1',
        ]);

        $product = Product::findOrFail($data['product_id']);
        $preview = $this->stock->hpp->previewRestockCost(
            $product,
            (float) $data['qty'],
            (int) $data['unit_cost']
        );

        return response()->json(['data' => $preview]);
    }

    /**
     * POST /api/restocks
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id'  => 'required|exists:products,id',
            'qty'         => 'required|numeric|min:0.001|decimal:0,3',
            'unit_cost'   => 'required|integer|min:1',
            'supplier'    => 'nullable|string|max:120',
            'note'        => 'nullable|string|max:255',
            'received_at' => 'nullable|date',
        ]);

        $restock = DB::transaction(function () use ($data, $request) {
            $product = Product::where('id', $data['product_id'])
                ->lockForUpdate()
                ->firstOrFail();

            return $this->stock->recordRestock(
                $product,
                (float) $data['qty'],
                (int) $data['unit_cost'],
                $data['supplier'] ?? null,
                $data['note'] ?? null,
                $request->user()->id,
                $data['received_at'] ?? null
            );
        });

        return response()->json([
            'data' => $this->formatRestock($restock->load(['product', 'user'])),
        ], 201);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function formatRestock(Restock $r): array
    {
        return [
            'id'           => $r->id,
            'product_id'   => $r->product_id,
            'product_name' => $r->product?->name,
            'user_name'    => $r->user?->name,
            'qty'          => $r->qty,
            'unit_cost'    => $r->unit_cost,
            'supplier'     => $r->supplier,
            'cost_before'  => $r->cost_before,
            'cost_after'   => $r->cost_after,
            'note'         => $r->note,
            'received_at'  => $r->received_at,
        ];
    }
}
