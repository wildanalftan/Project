<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Services\StockService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionController extends Controller
{
    public function __construct(private StockService $stock) {}

    /**
     * POST /api/transactions
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_uuid'    => 'nullable|uuid',
            'occurred_at'    => 'nullable|date',
            'table_label'    => 'nullable|string|max:30',
            'payment_method' => 'required|in:tunai,qris,kartu',
            'paid_amount'    => 'required|integer|min:0',
            'is_offline'     => 'sometimes|boolean',
            'items'          => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.qty'    => 'required|numeric|min:0.001',
        ]);

        // Idempotency: check if already processed
        if (!empty($data['client_uuid'])) {
            $existing = Transaction::where('client_uuid', $data['client_uuid'])->first();
            if ($existing) {
                return response()->json([
                    'data'      => $this->formatTransaction($existing->load('items')),
                    'duplicate' => true,
                ]);
            }
        }

        // Validate occurred_at within 24 hours of server time
        $occurredAt = isset($data['occurred_at'])
            ? Carbon::parse($data['occurred_at'])
            : now();

        if (abs($occurredAt->diffInHours(now())) > 24) {
            throw ValidationException::withMessages([
                'occurred_at' => ['Waktu transaksi tidak boleh berbeda lebih dari 24 jam dari waktu server.'],
            ]);
        }

        $taxPercent = (float) Setting::getValue('tax_percent', 10);

        $transaction = DB::transaction(function () use ($data, $occurredAt, $taxPercent, $request) {
            $userId     = $request->user()->id;
            $invoiceNo  = $this->stock->generateInvoiceNo($occurredAt);

            // Build items with locked products
            $itemsData   = [];
            $subtotal    = 0;
            $totalCogs   = 0;

            // Sort items by product_id to prevent deadlocks
            $sortedItems = collect($data['items'])->sortBy('product_id');

            // Placeholder transaction to get ID for movements
            $transaction = Transaction::create([
                'invoice_no'     => $invoiceNo,
                'client_uuid'    => $data['client_uuid'] ?? null,
                'user_id'        => $userId,
                'table_label'    => $data['table_label'] ?? null,
                'subtotal'       => 0,
                'tax_amount'     => 0,
                'total'          => 0,
                'payment_method' => $data['payment_method'],
                'paid_amount'    => $data['paid_amount'],
                'change_amount'  => 0,
                'total_cogs'     => 0,
                'gross_profit'   => 0,
                'is_offline'     => $data['is_offline'] ?? false,
                'occurred_at'    => $occurredAt,
                'synced_at'      => isset($data['is_offline']) && $data['is_offline'] ? now() : null,
            ]);

            foreach ($sortedItems as $item) {
                $product = Product::where('id', $item['product_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if (!$product->is_active) {
                    throw ValidationException::withMessages([
                        'items' => ["Produk {$product->name} tidak aktif."],
                    ]);
                }

                [$unitCost, $lineCogs] = $this->stock->deductForSale(
                    $product, (float) $item['qty'], $userId, $transaction
                );

                $lineTotal = (int) round($product->selling_price * $item['qty']);
                $lineProfit = $lineTotal - $lineCogs;

                $itemsData[] = [
                    'transaction_id' => $transaction->id,
                    'product_id'     => $product->id,
                    'product_name'   => $product->name,
                    'qty'            => $item['qty'],
                    'unit_price'     => $product->selling_price,
                    'unit_cost'      => $unitCost,
                    'line_total'     => $lineTotal,
                    'line_cogs'      => $lineCogs,
                    'line_profit'    => $lineProfit,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];

                $subtotal  += $lineTotal;
                $totalCogs += $lineCogs;
            }

            TransactionItem::insert($itemsData);

            $taxAmount    = (int) round($subtotal * $taxPercent / 100);
            $total        = $subtotal + $taxAmount;
            $changeAmount = max(0, $data['paid_amount'] - $total);
            $grossProfit  = $total - $totalCogs;

            // Validate paid_amount for tunai
            if ($data['payment_method'] === 'tunai' && $data['paid_amount'] < $total) {
                throw ValidationException::withMessages([
                    'paid_amount' => ['Uang diterima kurang dari total pembayaran.'],
                ]);
            }

            $transaction->update([
                'subtotal'     => $subtotal,
                'tax_amount'   => $taxAmount,
                'total'        => $total,
                'change_amount'=> $changeAmount,
                'total_cogs'   => $totalCogs,
                'gross_profit' => $grossProfit,
            ]);

            return $transaction;
        });

        return response()->json([
            'data' => $this->formatTransaction($transaction->load('items')),
        ], 201);
    }

    /**
     * GET /api/transactions
     */
    public function index(Request $request): JsonResponse
    {
        $query = Transaction::with('user')->orderByDesc('occurred_at');

        if ($from = $request->query('from')) {
            $query->where('occurred_at', '>=', Carbon::parse($from)->startOfDay());
        }
        if ($to = $request->query('to')) {
            $query->where('occurred_at', '<=', Carbon::parse($to)->endOfDay());
        }
        if ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }

        $paginated = $query->paginate(25);

        return response()->json([
            'data' => $paginated->map(fn($t) => $this->formatTransaction($t)),
            'meta' => [
                'total'        => $paginated->total(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/transactions/{id}
     */
    public function show(Transaction $transaction): JsonResponse
    {
        $transaction->load(['items', 'user']);
        return response()->json(['data' => $this->formatTransaction($transaction)]);
    }

    /**
     * POST /api/transactions/sync — offline queue batch sync
     */
    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'transactions'   => 'required|array|min:1|max:20',
        ]);

        $results = [];
        foreach ($request->transactions as $txData) {
            try {
                $fakeRequest = Request::create('', 'POST', $txData);
                $fakeRequest->headers->set('Authorization', $request->header('Authorization'));

                // Check duplicate first
                if (!empty($txData['client_uuid'])) {
                    $existing = Transaction::where('client_uuid', $txData['client_uuid'])->first();
                    if ($existing) {
                        $results[] = [
                            'client_uuid' => $txData['client_uuid'],
                            'status'      => 'duplicate',
                            'invoice_no'  => $existing->invoice_no,
                        ];
                        continue;
                    }
                }

                // Process via store
                $txData['is_offline'] = true;
                $mergedRequest        = new Request($txData);
                $mergedRequest->setUserResolver($request->getUserResolver());

                $response = $this->processSingleOfflineTx($txData, $request->user()->id);
                $results[] = [
                    'client_uuid' => $txData['client_uuid'] ?? null,
                    'status'      => 'accepted',
                    'invoice_no'  => $response->invoice_no,
                    'total'       => $response->total,
                ];
            } catch (ValidationException $e) {
                $results[] = [
                    'client_uuid' => $txData['client_uuid'] ?? null,
                    'status'      => 'failed',
                    'reason'      => collect($e->errors())->flatten()->first(),
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'client_uuid' => $txData['client_uuid'] ?? null,
                    'status'      => 'failed',
                    'reason'      => $e->getMessage(),
                ];
            }
        }

        return response()->json(['data' => $results]);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function processSingleOfflineTx(array $data, int $userId): Transaction
    {
        $taxPercent = (float) Setting::getValue('tax_percent', 10);
        $occurredAt = isset($data['occurred_at'])
            ? Carbon::parse($data['occurred_at'])
            : now();

        return DB::transaction(function () use ($data, $occurredAt, $taxPercent, $userId) {
            $invoiceNo   = $this->stock->generateInvoiceNo($occurredAt);
            $sortedItems = collect($data['items'])->sortBy('product_id');

            $transaction = Transaction::create([
                'invoice_no'     => $invoiceNo,
                'client_uuid'    => $data['client_uuid'] ?? null,
                'user_id'        => $userId,
                'table_label'    => $data['table_label'] ?? null,
                'subtotal'       => 0,
                'tax_amount'     => 0,
                'total'          => 0,
                'payment_method' => $data['payment_method'],
                'paid_amount'    => $data['paid_amount'],
                'change_amount'  => 0,
                'total_cogs'     => 0,
                'gross_profit'   => 0,
                'is_offline'     => true,
                'occurred_at'    => $occurredAt,
                'synced_at'      => now(),
            ]);

            $itemsData = [];
            $subtotal  = 0;
            $totalCogs = 0;

            foreach ($sortedItems as $item) {
                $product = Product::where('id', $item['product_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                [$unitCost, $lineCogs] = $this->stock->deductForSale(
                    $product, (float) $item['qty'], $userId, $transaction
                );

                $lineTotal  = (int) round($product->selling_price * $item['qty']);
                $lineProfit = $lineTotal - $lineCogs;

                $itemsData[] = [
                    'transaction_id' => $transaction->id,
                    'product_id'     => $product->id,
                    'product_name'   => $product->name,
                    'qty'            => $item['qty'],
                    'unit_price'     => $product->selling_price,
                    'unit_cost'      => $unitCost,
                    'line_total'     => $lineTotal,
                    'line_cogs'      => $lineCogs,
                    'line_profit'    => $lineProfit,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];

                $subtotal  += $lineTotal;
                $totalCogs += $lineCogs;
            }

            TransactionItem::insert($itemsData);

            $taxAmount   = (int) round($subtotal * $taxPercent / 100);
            $total       = $subtotal + $taxAmount;
            $grossProfit = $total - $totalCogs;

            $transaction->update([
                'subtotal'     => $subtotal,
                'tax_amount'   => $taxAmount,
                'total'        => $total,
                'change_amount'=> max(0, $data['paid_amount'] - $total),
                'total_cogs'   => $totalCogs,
                'gross_profit' => $grossProfit,
            ]);

            return $transaction;
        });
    }

    private function formatTransaction(Transaction $t): array
    {
        return [
            'id'             => $t->id,
            'invoice_no'     => $t->invoice_no,
            'client_uuid'    => $t->client_uuid,
            'user_name'      => $t->user?->name,
            'table_label'    => $t->table_label,
            'subtotal'       => $t->subtotal,
            'tax_amount'     => $t->tax_amount,
            'total'          => $t->total,
            'payment_method' => $t->payment_method,
            'paid_amount'    => $t->paid_amount,
            'change_amount'  => $t->change_amount,
            'total_cogs'     => $t->total_cogs,
            'gross_profit'   => $t->gross_profit,
            'status'         => $t->status,
            'is_offline'     => $t->is_offline,
            'occurred_at'    => $t->occurred_at,
            'items'          => $t->relationLoaded('items') ? $t->items->map(fn($i) => [
                'product_id'   => $i->product_id,
                'product_name' => $i->product_name,
                'qty'          => $i->qty,
                'unit_price'   => $i->unit_price,
                'unit_cost'    => $i->unit_cost,
                'line_total'   => $i->line_total,
                'line_cogs'    => $i->line_cogs,
                'line_profit'  => $i->line_profit,
            ]) : [],
        ];
    }
}
