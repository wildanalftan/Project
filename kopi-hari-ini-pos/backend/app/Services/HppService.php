<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Setting;
use App\Models\StockBatch;

class HppService
{
    /**
     * Get the active HPP calculation method from settings.
     */
    public function getMethod(): string
    {
        return Setting::getValue('hpp_method', 'weighted_average');
    }

    /**
     * Calculate the new weighted average cost after a restock.
     * Formula: ((Q_old * C_old) + (Q_in * C_in)) / (Q_old + Q_in)
     *
     * Edge case: if old stock is 0, new cost is simply the incoming cost.
     * Result is rounded to nearest integer (half-up) as per spec.
     */
    public function calculateWeightedAverage(
        float $qtyOld,
        int   $costOld,
        float $qtyIn,
        int   $costIn
    ): int {
        if ($qtyOld <= 0) {
            return $costIn;
        }

        $totalValue = ($qtyOld * $costOld) + ($qtyIn * $costIn);
        $totalQty   = $qtyOld + $qtyIn;

        return (int) round($totalValue / $totalQty);
    }

    /**
     * Preview the new HPP without persisting — used by /restocks/preview endpoint.
     */
    public function previewRestockCost(Product $product, float $qtyIn, int $costIn): array
    {
        $method = $this->getMethod();

        if ($method === 'fifo') {
            // Under FIFO, the new cost doesn't change immediately on incoming —
            // it's recalculated from remaining batches, but for preview we show
            // weighted average of existing batches + new batch.
            $newCost = $this->calculateWeightedAverage(
                $product->stock_qty,
                $product->current_cost,
                $qtyIn,
                $costIn
            );
        } else {
            $newCost = $this->calculateWeightedAverage(
                $product->stock_qty,
                $product->current_cost,
                $qtyIn,
                $costIn
            );
        }

        return [
            'method'       => $method,
            'cost_before'  => $product->current_cost,
            'cost_after'   => $newCost,
            'stock_before' => $product->stock_qty,
            'stock_after'  => $product->stock_qty + $qtyIn,
        ];
    }

    /**
     * Consume stock for a sale using FIFO method.
     * Returns the total COGS for the given qty consumed.
     *
     * @param Product $product  (must already be locked with lockForUpdate)
     * @param float   $qtyNeeded
     * @return int    total COGS (rupiah)
     */
    public function consumeFifo(Product $product, float $qtyNeeded): int
    {
        $totalCogs   = 0;
        $remaining   = $qtyNeeded;

        $batches = StockBatch::where('product_id', $product->id)
            ->where('qty_remaining', '>', 0)
            ->orderBy('received_at')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, $batch->qty_remaining);
            $totalCogs += (int) round($take * $batch->unit_cost);

            $batch->qty_remaining -= $take;
            $batch->save();

            $remaining -= $take;
        }

        // Edge case: batches exhausted but still remaining quantity needed
        if ($remaining > 0) {
            // Use last known cost and log as adjustment
            $lastBatch = StockBatch::where('product_id', $product->id)
                ->orderByDesc('received_at')
                ->first();
            $lastCost  = $lastBatch ? $lastBatch->unit_cost : $product->current_cost;
            $totalCogs += (int) round($remaining * $lastCost);
        }

        return $totalCogs;
    }

    /**
     * Recalculate current_cost from remaining FIFO batches (weighted average of remaining).
     * Called after each FIFO sale to keep current_cost accurate for asset valuation.
     */
    public function recalcCurrentCostFromBatches(Product $product): int
    {
        $batches = StockBatch::where('product_id', $product->id)
            ->where('qty_remaining', '>', 0)
            ->get();

        if ($batches->isEmpty()) {
            return $product->current_cost; // keep last known cost
        }

        $totalValue = $batches->sum(fn($b) => $b->qty_remaining * $b->unit_cost);
        $totalQty   = $batches->sum('qty_remaining');

        return $totalQty > 0 ? (int) round($totalValue / $totalQty) : $product->current_cost;
    }
}
