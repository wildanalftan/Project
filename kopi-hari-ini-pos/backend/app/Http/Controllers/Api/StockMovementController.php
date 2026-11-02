<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    /**
     * GET /api/stock-movements
     */
    public function index(Request $request): JsonResponse
    {
        $query = StockMovement::with(['product', 'user'])->orderByDesc('occurred_at');

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($from = $request->query('from')) {
            $query->where('occurred_at', '>=', Carbon::parse($from)->startOfDay());
        }
        if ($to = $request->query('to')) {
            $query->where('occurred_at', '<=', Carbon::parse($to)->endOfDay());
        }
        if ($productId = $request->query('product_id')) {
            $query->where('product_id', $productId);
        }

        $paginated = $query->paginate(25);

        return response()->json([
            'data' => $paginated->map(fn($m) => $this->formatMovement($m)),
            'meta' => [
                'total'        => $paginated->total(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/stock-movements/export — CSV download
     */
    public function export(Request $request)
    {
        $query = StockMovement::with(['product', 'user'])->orderByDesc('occurred_at');

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($from = $request->query('from')) {
            $query->where('occurred_at', '>=', Carbon::parse($from)->startOfDay());
        }
        if ($to = $request->query('to')) {
            $query->where('occurred_at', '<=', Carbon::parse($to)->endOfDay());
        }
        if ($productId = $request->query('product_id')) {
            $query->where('product_id', $productId);
        }

        $movements = $query->get();
        $filename  = 'riwayat-stok-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        $callback = function () use ($movements) {
            $handle = fopen('php://output', 'w');
            // BOM for Excel UTF-8
            fputs($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Waktu', 'Jenis', 'Produk', 'SKU', 'Sebelum', 'Perubahan', 'Sesudah',
                              'HPP Sebelum', 'HPP Sesudah', 'Referensi', 'Pengguna', 'Catatan']);

            foreach ($movements as $m) {
                fputcsv($handle, [
                    $m->occurred_at?->format('Y-m-d H:i:s'),
                    $m->type,
                    $m->product?->name,
                    $m->product?->sku,
                    $m->qty_before,
                    $m->qty_change,
                    $m->qty_after,
                    $m->cost_before,
                    $m->cost_after,
                    $m->reference_type . '#' . $m->reference_id,
                    $m->user?->name,
                    $m->note,
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function formatMovement(StockMovement $m): array
    {
        return [
            'id'             => $m->id,
            'product_id'     => $m->product_id,
            'product_name'   => $m->product?->name,
            'product_sku'    => $m->product?->sku,
            'user_name'      => $m->user?->name,
            'type'           => $m->type,
            'reference_type' => $m->reference_type,
            'reference_id'   => $m->reference_id,
            'qty_before'     => $m->qty_before,
            'qty_change'     => $m->qty_change,
            'qty_after'      => $m->qty_after,
            'cost_before'    => $m->cost_before,
            'cost_after'     => $m->cost_after,
            'note'           => $m->note,
            'occurred_at'    => $m->occurred_at,
        ];
    }
}
