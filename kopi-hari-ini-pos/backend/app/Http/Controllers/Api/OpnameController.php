<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OpnameItem;
use App\Models\OpnameSession;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OpnameController extends Controller
{
    public function __construct(private StockService $stock) {}

    /**
     * GET /api/opname
     */
    public function index(): JsonResponse
    {
        $sessions = OpnameSession::with('user')
            ->orderByDesc('session_date')
            ->paginate(20);

        return response()->json([
            'data' => $sessions->map(fn($s) => $this->formatSession($s)),
            'meta' => ['total' => $sessions->total()],
        ]);
    }

    /**
     * POST /api/opname — create new session with all active products
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_date' => 'nullable|date',
            'note'         => 'nullable|string|max:255',
        ]);

        $session = DB::transaction(function () use ($data, $request) {
            $session = OpnameSession::create([
                'user_id'      => $request->user()->id,
                'session_date' => $data['session_date'] ?? today(),
                'note'         => $data['note'] ?? null,
            ]);

            // Snapshot all active products with current stock
            $products = Product::where('is_active', true)->get();
            $items    = $products->map(fn($p) => [
                'opname_session_id' => $session->id,
                'product_id'        => $p->id,
                'system_qty'        => $p->stock_qty,
                'physical_qty'      => null,
                'difference'        => null,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            OpnameItem::insert($items->toArray());
            return $session;
        });

        return response()->json([
            'data' => $this->formatSession($session->load(['user', 'items.product'])),
        ], 201);
    }

    /**
     * GET /api/opname/{id}
     */
    public function show(OpnameSession $opname): JsonResponse
    {
        $opname->load(['user', 'items.product.category']);
        return response()->json(['data' => $this->formatSession($opname)]);
    }

    /**
     * PUT /api/opname/{id}/items — save draft physical_qty
     */
    public function updateItems(Request $request, OpnameSession $opname): JsonResponse
    {
        if ($opname->isLocked()) {
            return response()->json(['message' => 'Sesi opname sudah dikunci dan tidak bisa diubah.'], 422);
        }

        $data = $request->validate([
            'items'                  => 'required|array',
            'items.*.product_id'     => 'required|integer|exists:products,id',
            'items.*.physical_qty'   => 'nullable|numeric|min:0|decimal:0,3',
            'items.*.note'           => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($data, $opname) {
            foreach ($data['items'] as $item) {
                $row = OpnameItem::where('opname_session_id', $opname->id)
                    ->where('product_id', $item['product_id'])
                    ->first();

                if (!$row) {
                    continue;
                }

                $physicalQty = isset($item['physical_qty']) ? (float) $item['physical_qty'] : null;
                $difference  = $physicalQty !== null ? $physicalQty - $row->system_qty : null;

                $row->update([
                    'physical_qty' => $physicalQty,
                    'difference'   => $difference,
                    'note'         => $item['note'] ?? $row->note,
                ]);
            }
        });

        $opname->load(['user', 'items.product']);
        return response()->json(['data' => $this->formatSession($opname)]);
    }

    /**
     * POST /api/opname/{id}/lock — lock session, adjust stock
     */
    public function lock(Request $request, OpnameSession $opname): JsonResponse
    {
        if ($opname->isLocked()) {
            return response()->json(['message' => 'Sesi sudah dikunci.'], 422);
        }

        DB::transaction(function () use ($opname, $request) {
            $items = OpnameItem::where('opname_session_id', $opname->id)
                ->whereNotNull('physical_qty')
                ->whereRaw('ABS(difference) > 0.001')
                ->get();

            foreach ($items as $item) {
                $product = Product::where('id', $item->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->stock->applyOpnameAdjustment(
                    $product,
                    (float) $item->physical_qty,
                    $opname->id,
                    $request->user()->id,
                    $item->note
                );

                // Refresh system_qty snapshot in opname item
                $item->update(['system_qty' => $item->physical_qty]);
            }

            $opname->update([
                'status'    => 'locked',
                'locked_at' => now(),
            ]);
        });

        return response()->json([
            'data'    => $this->formatSession($opname->fresh()->load('user')),
            'message' => 'Sesi opname berhasil dikunci dan stok disesuaikan.',
        ]);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function formatSession(OpnameSession $s): array
    {
        $data = [
            'id'           => $s->id,
            'user_name'    => $s->user?->name,
            'session_date' => $s->session_date?->toDateString(),
            'status'       => $s->status,
            'locked_at'    => $s->locked_at,
            'note'         => $s->note,
            'created_at'   => $s->created_at,
        ];

        if ($s->relationLoaded('items')) {
            $data['items'] = $s->items->map(fn($i) => [
                'id'             => $i->id,
                'product_id'     => $i->product_id,
                'product_name'   => $i->product?->name,
                'product_sku'    => $i->product?->sku,
                'category_name'  => $i->product?->category?->name,
                'unit'           => $i->product?->unit,
                'system_qty'     => $i->system_qty,
                'physical_qty'   => $i->physical_qty,
                'difference'     => $i->difference,
                'note'           => $i->note,
            ]);
            $counted = $s->items->whereNotNull('physical_qty')->count();
            $data['stats'] = [
                'total_products' => $s->items->count(),
                'counted'        => $counted,
                'deficit_count'  => $s->items->where('difference', '<', 0)->count(),
                'surplus_count'  => $s->items->where('difference', '>', 0)->count(),
            ];
        }

        return $data;
    }
}
