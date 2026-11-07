<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Support\DashboardCache;
use App\Support\Decimal;
use Carbon\CarbonInterface;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $today = today();

        $metrics = DashboardCache::remember(
            (string) tenant()->getTenantKey(),
            $today->toDateString(),
            fn () => $this->metrics($today),
        );

        return Inertia::render('Dashboard', [
            ...$metrics,
            'currentMonth' => [
                'from' => $today->copy()->startOfMonth()->toDateString(),
                'to' => $today->toDateString(),
            ],
        ]);
    }

    /**
     * @return array{
     *     invoiceCounts: array{total: int, unpaid: int, partial: int, paid: int, overdue: int},
     *     receivedThisMonth: string,
     *     invoiceBalances: array{outstanding: string, overdue: string, paid: string}
     * }
     */
    private function metrics(CarbonInterface $today): array
    {
        $counts = Invoice::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) AS unpaid', ['unpaid'])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) AS partial', ['partial'])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) AS paid', ['paid'])
            ->selectRaw(
                'COUNT(CASE WHEN status <> ? AND due_date < ? THEN 1 END) AS overdue',
                ['paid', $today->toDateString()],
            )
            ->toBase()
            ->first();

        $monthStart = $today->copy()->startOfMonth();

        $receivedThisMonth = Payment::query()
            ->where('paid_at', '>=', $monthStart->toDateString())
            ->where('paid_at', '<', $today->copy()->addDay()->toDateString())
            ->sum('amount');

        $paymentsByInvoice = Payment::query()
            ->select('invoice_id')
            ->selectRaw('SUM(amount) AS paid_amount')
            ->groupBy('invoice_id')
            ->toBase();

        $balance = 'invoices.total - COALESCE(invoice_payments.paid_amount, 0)';

        $balances = Invoice::query()
            ->leftJoinSub($paymentsByInvoice, 'invoice_payments', function ($join) {
                $join->on('invoices.id', '=', 'invoice_payments.invoice_id');
            })
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN invoices.status <> ? THEN $balance ELSE 0 END), 0) AS outstanding",
                ['paid'],
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN invoices.status <> ? AND invoices.due_date < ? THEN $balance ELSE 0 END), 0) AS overdue",
                ['paid', $today->toDateString()],
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN invoices.status = ? THEN invoices.total ELSE 0 END), 0) AS paid',
                ['paid'],
            )
            ->toBase()
            ->first();

        return [
            'invoiceCounts' => [
                'total' => (int) $counts->total,
                'unpaid' => (int) $counts->unpaid,
                'partial' => (int) $counts->partial,
                'paid' => (int) $counts->paid,
                'overdue' => (int) $counts->overdue,
            ],
            'receivedThisMonth' => Decimal::format($receivedThisMonth),
            'invoiceBalances' => [
                'outstanding' => Decimal::format($balances->outstanding),
                'overdue' => Decimal::format($balances->overdue),
                'paid' => Decimal::format($balances->paid),
            ],
        ];
    }
}
