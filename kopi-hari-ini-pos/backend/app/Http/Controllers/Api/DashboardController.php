<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private ReportService $report) {}

    /**
     * GET /api/dashboard
     */
    public function index(Request $request): JsonResponse
    {
        $period = $request->query('period', 'today');
        $data   = $this->report->getDashboardData($period);
        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/hpp
     */
    public function hpp(Request $request): JsonResponse
    {
        $from = $request->query('from')
            ? Carbon::parse($request->query('from'))->startOfDay()
            : Carbon::today()->startOfDay();
        $to   = $request->query('to')
            ? Carbon::parse($request->query('to'))->endOfDay()
            : Carbon::today()->endOfDay();

        $data = $this->report->getHppSummary($from, $to);
        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/reports/profit-loss
     */
    public function profitLoss(Request $request): JsonResponse
    {
        $year  = (int) ($request->query('year', now()->year));
        $month = (int) ($request->query('month', now()->month));

        $data  = $this->report->getProfitLoss($year, $month);
        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/reports/profit-loss/export
     */
    public function exportProfitLoss(Request $request)
    {
        $year   = (int) ($request->query('year', now()->year));
        $month  = (int) ($request->query('month', now()->month));
        $format = $request->query('format', 'csv');

        $data     = $this->report->getProfitLoss($year, $month);
        $filename = "laporan-laba-rugi-{$year}-{$month:02d}";

        if ($format === 'csv') {
            return $this->exportCsv($data, $filename);
        }

        // PDF placeholder — returns CSV if DOMPDF not installed
        return $this->exportCsv($data, $filename);
    }

    /**
     * POST /api/reports/expenses
     */
    public function storeExpense(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period'   => 'required|date',
            'category' => 'required|string|max:100',
            'amount'   => 'required|integer|min:1',
            'note'     => 'nullable|string|max:255',
        ]);

        $expense = \App\Models\OperatingExpense::create([
            'period'   => Carbon::parse($data['period'])->startOfMonth()->toDateString(),
            'category' => $data['category'],
            'amount'   => $data['amount'],
            'note'     => $data['note'] ?? null,
        ]);

        return response()->json(['data' => $expense], 201);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function exportCsv(array $data, string $filename)
    {
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename={$filename}.csv",
        ];

        $callback = function () use ($data) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Keterangan', 'Jumlah (Rp)']);

            fputcsv($handle, ['LAPORAN LABA RUGI - ' . strtoupper($data['label']), '']);
            fputcsv($handle, ['Omzet Bruto', $data['revenue']]);
            fputcsv($handle, ['Total HPP', -$data['total_cogs']]);
            fputcsv($handle, ['Laba Kotor', $data['gross_profit']]);
            fputcsv($handle, ['Margin Kotor', $data['gross_margin'] . '%']);
            fputcsv($handle, ['', '']);
            fputcsv($handle, ['BEBAN OPERASIONAL', '']);

            foreach ($data['expenses'] as $exp) {
                fputcsv($handle, [$exp['category'], -$exp['amount']]);
            }

            fputcsv($handle, ['Total Beban', -$data['total_expenses']]);
            fputcsv($handle, ['', '']);
            fputcsv($handle, ['Laba Bersih', $data['net_profit']]);
            fputcsv($handle, ['Margin Bersih', $data['net_margin'] . '%']);
            fputcsv($handle, ['Nilai Aset Persediaan', $data['inventory_value']]);

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
