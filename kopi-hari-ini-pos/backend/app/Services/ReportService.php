<?php

namespace App\Services;

use App\Models\OperatingExpense;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function __construct(private StockService $stock) {}

    /**
     * Dashboard summary: today's metrics and comparisons.
     */
    public function getDashboardData(string $period = 'today'): array
    {
        [$from, $to] = $this->getPeriodRange($period);
        $yesterday   = [Carbon::yesterday()->startOfDay(), Carbon::yesterday()->endOfDay()];

        $current  = $this->getPeriodMetrics($from, $to);
        $previous = $this->getPeriodMetrics(...$yesterday);

        $last7Days = $this->getLast7DaysSeries();
        $topItems  = $this->getTopProducts($from, $to, 10);

        $stockSummary = [
            'sku_habis'   => Product::where('is_active', true)->where('stock_qty', '<=', 0)->count(),
            'sku_menipis' => Product::where('is_active', true)
                ->whereRaw('stock_qty > 0 AND stock_qty <= low_stock_threshold')
                ->count(),
        ];

        return [
            'period'          => $period,
            'current'         => $current,
            'previous'        => $previous,
            'last_7_days'     => $last7Days,
            'top_products'    => $topItems,
            'inventory_value' => $this->stock->getTotalInventoryValue(),
            'stock_summary'   => $stockSummary,
        ];
    }

    /**
     * Monthly profit & loss report.
     */
    public function getProfitLoss(int $year, int $month): array
    {
        $from = Carbon::create($year, $month, 1)->startOfDay();
        $to   = $from->copy()->endOfMonth()->endOfDay();

        $revenue = (int) Transaction::where('status', 'completed')
            ->whereBetween('occurred_at', [$from, $to])
            ->sum('total');

        $totalCogs = (int) Transaction::where('status', 'completed')
            ->whereBetween('occurred_at', [$from, $to])
            ->sum('total_cogs');

        $grossProfit = $revenue - $totalCogs;

        $period    = $from->format('Y-m-01');
        $expenses  = OperatingExpense::where('period', $period)->get();
        $totalExp  = $expenses->sum('amount');

        $netProfit    = $grossProfit - $totalExp;
        $netMargin    = $revenue > 0 ? round($netProfit / $revenue * 100, 1) : 0;
        $grossMargin  = $revenue > 0 ? round($grossProfit / $revenue * 100, 1) : 0;

        // 6-month gross margin trend
        $trendData = [];
        for ($i = 5; $i >= 0; $i--) {
            $mFrom = Carbon::create($year, $month, 1)->subMonths($i)->startOfDay();
            $mTo   = $mFrom->copy()->endOfMonth()->endOfDay();
            $mRev  = (int) Transaction::where('status', 'completed')
                ->whereBetween('occurred_at', [$mFrom, $mTo])
                ->sum('total');
            $mCogs = (int) Transaction::where('status', 'completed')
                ->whereBetween('occurred_at', [$mFrom, $mTo])
                ->sum('total_cogs');
            $mMargin = $mRev > 0 ? round(($mRev - $mCogs) / $mRev * 100, 1) : 0;
            $trendData[] = [
                'month'        => $mFrom->format('Y-m'),
                'label'        => $mFrom->locale('id')->isoFormat('MMM YYYY'),
                'revenue'      => $mRev,
                'cogs'         => $mCogs,
                'gross_margin' => $mMargin,
            ];
        }

        return [
            'period'        => $from->format('Y-m'),
            'label'         => $from->locale('id')->isoFormat('MMMM YYYY'),
            'revenue'       => $revenue,
            'total_cogs'    => $totalCogs,
            'gross_profit'  => $grossProfit,
            'gross_margin'  => $grossMargin,
            'expenses'      => $expenses->map(fn($e) => [
                'category' => $e->category,
                'amount'   => $e->amount,
            ]),
            'total_expenses'=> $totalExp,
            'net_profit'    => $netProfit,
            'net_margin'    => $netMargin,
            'margin_trend'  => $trendData,
            'inventory_value' => $this->stock->getTotalInventoryValue(),
        ];
    }

    /**
     * HPP per product summary for a period.
     */
    public function getHppSummary(Carbon $from, Carbon $to): array
    {
        $items = TransactionItem::with('product')
            ->whereHas('transaction', fn($q) => $q->where('status', 'completed')
                ->whereBetween('occurred_at', [$from, $to]))
            ->select(
                'product_id',
                'product_name',
                DB::raw('SUM(qty) as total_qty'),
                DB::raw('SUM(line_total) as total_revenue'),
                DB::raw('SUM(line_cogs) as total_cogs'),
                DB::raw('SUM(line_profit) as total_profit'),
                DB::raw('MAX(unit_price) as unit_price'),
                DB::raw('MAX(unit_cost) as unit_cost')
            )
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('total_revenue')
            ->get();

        $totalRevenue = (int) Transaction::where('status', 'completed')
            ->whereBetween('occurred_at', [$from, $to])
            ->sum('total');
        $totalCogs    = (int) Transaction::where('status', 'completed')
            ->whereBetween('occurred_at', [$from, $to])
            ->sum('total_cogs');
        $grossProfit  = $totalRevenue - $totalCogs;
        $avgMargin    = $totalRevenue > 0
            ? round($grossProfit / $totalRevenue * 100, 1)
            : 0;

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_cogs'    => $totalCogs,
                'gross_profit'  => $grossProfit,
                'avg_margin'    => $avgMargin,
            ],
            'products' => $items->map(function ($item) {
                $margin = $item->unit_price > 0
                    ? round(($item->unit_price - $item->unit_cost) / $item->unit_price * 100, 1)
                    : 0;
                return [
                    'product_id'    => $item->product_id,
                    'product_name'  => $item->product_name,
                    'total_qty'     => (float) $item->total_qty,
                    'unit_cost'     => (int) $item->unit_cost,
                    'unit_price'    => (int) $item->unit_price,
                    'profit_per_unit'=> (int) ($item->unit_price - $item->unit_cost),
                    'margin'        => $margin,
                    'total_revenue' => (int) $item->total_revenue,
                    'total_cogs'    => (int) $item->total_cogs,
                    'total_profit'  => (int) $item->total_profit,
                ];
            }),
        ];
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function getPeriodRange(string $period): array
    {
        return match ($period) {
            'week'  => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
            'month' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
            default => [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()],
        };
    }

    private function getPeriodMetrics(Carbon $from, Carbon $to): array
    {
        $q = Transaction::where('status', 'completed')->whereBetween('occurred_at', [$from, $to]);

        $revenue      = (int) (clone $q)->sum('total');
        $cogs         = (int) (clone $q)->sum('total_cogs');
        $gross        = $revenue - $cogs;
        $count        = (clone $q)->count();
        $avgReceipt   = $count > 0 ? (int) round($revenue / $count) : 0;
        $margin       = $revenue > 0 ? round($gross / $revenue * 100, 1) : 0;

        return [
            'revenue'      => $revenue,
            'total_cogs'   => $cogs,
            'gross_profit' => $gross,
            'margin'       => $margin,
            'tx_count'     => $count,
            'avg_receipt'  => $avgReceipt,
        ];
    }

    private function getLast7DaysSeries(): array
    {
        $series = [];
        for ($i = 6; $i >= 0; $i--) {
            $day  = Carbon::today()->subDays($i);
            $from = $day->copy()->startOfDay();
            $to   = $day->copy()->endOfDay();

            $revenue = (int) Transaction::where('status', 'completed')
                ->whereBetween('occurred_at', [$from, $to])
                ->sum('total');
            $cogs    = (int) Transaction::where('status', 'completed')
                ->whereBetween('occurred_at', [$from, $to])
                ->sum('total_cogs');

            $series[] = [
                'date'    => $day->format('Y-m-d'),
                'label'   => $day->locale('id')->isoFormat('ddd D/M'),
                'revenue' => $revenue,
                'cogs'    => $cogs,
                'profit'  => $revenue - $cogs,
            ];
        }
        return $series;
    }

    private function getTopProducts(Carbon $from, Carbon $to, int $limit = 10): array
    {
        return TransactionItem::whereHas(
                'transaction',
                fn($q) => $q->where('status', 'completed')->whereBetween('occurred_at', [$from, $to])
            )
            ->select(
                'product_id',
                'product_name',
                DB::raw('SUM(qty) as total_qty'),
                DB::raw('SUM(line_total) as total_revenue')
            )
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get()
            ->map(fn($i) => [
                'product_id'   => $i->product_id,
                'product_name' => $i->product_name,
                'total_qty'    => (float) $i->total_qty,
                'total_revenue'=> (int) $i->total_revenue,
            ])
            ->toArray();
    }
}
