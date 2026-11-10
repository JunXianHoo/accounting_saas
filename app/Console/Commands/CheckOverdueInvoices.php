<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckOverdueInvoices extends Command
{
    protected $signature = 'invoices:check-overdue';

    protected $description = 'Check overdue invoices for each company';

    public function handle(): int
    {
        $checkedAt = now();
        $today = $checkedAt->toDateString();

        foreach (Tenant::query()->lazyById() as $company) {
            try {
                tenancy()->initialize($company);

                $paymentTotal = Payment::query()
                    ->selectRaw('ROUND(COALESCE(SUM(amount), 0), 2)')
                    ->whereColumn('payments.invoice_id', 'invoices.id');

                $overdueInvoices = Invoice::query()
                    ->where('due_date', '<', $today)
                    ->where('total', '>', $paymentTotal)
                    ->toBase();

                $newlyDetected = (clone $overdueInvoices)
                    ->whereNull('overdue_detected_at')
                    ->update(['overdue_detected_at' => $checkedAt]);
                $overdueCount = $overdueInvoices->count();

                Log::info('Overdue invoice check completed', [
                    'tenant_id' => $company->id,
                    'overdue_count' => $overdueCount,
                    'newly_detected_count' => $newlyDetected,
                    'checked_on' => $today,
                ]);

                $this->line("Company {$company->id} — {$overdueCount} overdue invoice(s)");
            } finally {
                tenancy()->end();
            }
        }

        return self::SUCCESS;
    }
}
