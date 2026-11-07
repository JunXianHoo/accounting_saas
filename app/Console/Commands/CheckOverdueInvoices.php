<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Database\Models\Tenant;

class CheckOverdueInvoices extends Command
{
    protected $signature = 'invoices:check-overdue';

    protected $description = 'Check overdue invoices for each company';

    public function handle(): int
    {
        $today = today()->toDateString();

        foreach (Tenant::query()->lazyById() as $company) {
            try {
                tenancy()->initialize($company);

                $overdueInvoices = Invoice::query()
                    ->where('due_date', '<', $today)
                    ->select(['invoices.id', 'invoices.total'])
                    ->withPaymentTotals();

                $overdueCount = DB::query()
                    ->fromSub($overdueInvoices->toBase(), 'overdue_invoices')
                    ->whereRaw('COALESCE(total_paid, 0) < total')
                    ->count();

                Log::info('Overdue invoice check completed', [
                    'tenant_id' => $company->id,
                    'overdue_count' => $overdueCount,
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
