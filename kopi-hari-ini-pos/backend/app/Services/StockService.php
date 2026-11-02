<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Restock;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    public function __construct(private HppService $hpp) {}

    /**
     * Record a restock: add stock, calculate new HPP, write movement.
     * Must be called inside a DB::transaction() with the product locked.
     */
    public function recordRestock(
        Product $product,
        float   $qty,
        int     $unitCost,
        ?string $supplier,
        ?string $note,
        int     $userId,
        ?string $receivedAt = null
    ): Restock {
        $costBefore = $product->current_cost;
        $qtyBefore  = $product->stock_qty;

        // Calculate new HPP
        $method = $this->hpp->getMethod();
        $newCost = $this->hpp->calculateWeightedAverage(
            $product->stock_qty,
            $product->current_cost,
            $qty,
            $unitCost
        );

        // Create restock record
        $restock = Restock::create([
            'product_id'  => $product->id,
            'user_id'     => $userId,
            'qty'         => $qty,
            'unit_cost'   => $unitCost,
            'supplier'    => $supplier,
            'cost_before' => $costBefore,
            'cost_after'  => $newCost,
            'note'        => $note,
            'received_at' => $receivedAt ?? now(),
        ]);

        // Create FIFO batch
        StockBatch::create([
            'product_id'   => $product->id,
            'restock_id'   => $restock->id,
            'qty_in'       => $qty,
            'qty_remaining'=> $qty,
            'unit_cost'    => $unitCost,
            'received_at'  => $receivedAt ?? now(),
        ]);

        // Update product stock and cost (direct query bypasses guarded protection)
        $newQty = $qtyBefore + $qty;
        Product::where('id', $product->id)->update([
            'stock_qty'    => $newQty,
            'current_cost' => $newCost,
        ]);

        // Refresh the model
        $product->refresh();

        // Write stock movement
        StockMovement::create([
            'product_id'     => $product->id,
            'user_id'        => $userId,
            'type'           => 'restock',
            'reference_type' => 'Restock',
            'reference_id'   => $restock->id,
            'qty_before'     => $qtyBefore,
            'qty_change'     => $qty,
            'qty_after'      => $newQty,
            'cost_before'    => $costBefore,
            'cost_after'     => $newCost,
            'note'           => $note,
            'occurred_at'    => $receivedAt ?? now(),
        ]);

        return $restock;
    }

    /**
     * Deduct stock for a sale. Returns [unit_cost, line_cogs] for a single product.
     * Must be called inside a DB::transaction() with the product locked.
     *
     * @throws ValidationException if stock insufficient
     */
    public function deductForSale(Product $product, float $qty, int $userId, Transaction $transaction): array
    {
        // Guard: stock cannot go negative via kasir
        if ($product->stock_qty < $qty) {
            throw ValidationException::withMessages([
                'items' => ["Stok {$product->name} tidak mencukupi (tersisa {$product->stock_qty})."],
            ]);
        }

        $method     = $this->hpp->getMethod();
        $costBefore = $product->current_cost;
        $qtyBefore  = $product->stock_qty;

        if ($method === 'fifo') {
            $lineCogs = $this->hpp->consumeFifo($product, $qty);
            $newQty   = $qtyBefore - $qty;
            // Recalc current_cost from remaining batches
            $newCost  = $this->hpp->recalcCurrentCostFromBatches($product);
            $unitCost = $qty > 0 ? (int) round($lineCogs / $qty) : $costBefore;
        } else {
            // Weighted average: cost per unit stays same on sale
            $unitCost = $costBefore;
            $lineCogs = (int) round($qty * $unitCost);
            $newQty   = $qtyBefore - $qty;
            $newCost  = $costBefore; // does not change on sale under WA
        }

        // Update product stock and cost
        Product::where('id', $product->id)->update([
            'stock_qty'    => $newQty,
            'current_cost' => $newCost,
        ]);

        // Write stock movement
        StockMovement::create([
            'product_id'     => $product->id,
            'user_id'        => $userId,
            'type'           => 'sale',
            'reference_type' => 'Transaction',
            'reference_id'   => $transaction->id,
            'qty_before'     => $qtyBefore,
            'qty_change'     => -$qty,
            'qty_after'      => $newQty,
            'cost_before'    => $costBefore,
            'cost_after'     => $newCost,
            'note'           => "Penjualan struk {$transaction->invoice_no}",
            'occurred_at'    => $transaction->occurred_at,
        ]);

        return [$unitCost, $lineCogs];
    }

    /**
     * Apply opname adjustments for locked session.
     * Must be called inside a DB::transaction() with product locked.
     */
    public function applyOpnameAdjustment(
        Product $product,
        float   $physicalQty,
        int     $sessionId,
        int     $userId,
        ?string $note
    ): void {
        $qtyBefore  = $product->stock_qty;
        $qtyChange  = $physicalQty - $qtyBefore;
        $costBefore = $product->current_cost;

        Product::where('id', $product->id)->update([
            'stock_qty' => $physicalQty,
            // HPP does not change on opname
        ]);

        StockMovement::create([
            'product_id'     => $product->id,
            'user_id'        => $userId,
            'type'           => 'opname',
            'reference_type' => 'OpnameSession',
            'reference_id'   => $sessionId,
            'qty_before'     => $qtyBefore,
            'qty_change'     => $qtyChange,
            'qty_after'      => $physicalQty,
            'cost_before'    => $costBefore,
            'cost_after'     => $costBefore,
            'note'           => $note ?? 'Penyesuaian stock opname',
            'occurred_at'    => now(),
        ]);
    }

    /**
     * Calculate total inventory asset value = sum(stock_qty * current_cost) for all active products.
     */
    public function getTotalInventoryValue(): int
    {
        return (int) Product::where('is_active', true)
            ->get()
            ->sum(fn($p) => $p->stock_qty * $p->current_cost);
    }

    /**
     * Generate the next daily invoice number: TRX-MMDD-XXXX.
     * Example: TRX-1102-0001 for the first transaction on Nov 2.
     * Must be called inside a transaction to prevent race conditions.
     */
    public function generateInvoiceNo(\Carbon\Carbon $occurredAt): string
    {
        $prefix  = 'TRX-' . $occurredAt->format('md') . '-';
        $lastNo  = Transaction::where('invoice_no', 'like', $prefix . '%')
            ->lockForUpdate()
            ->count();

        return $prefix . str_pad($lastNo + 1, 4, '0', STR_PAD_LEFT);
    }
}
