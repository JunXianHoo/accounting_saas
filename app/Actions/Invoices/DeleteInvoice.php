<?php

namespace App\Actions\Invoices;

use App\Models\Invoice;
use App\Support\DashboardCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class DeleteInvoice
{
    public function handle(Invoice $invoice): void
    {
        if (! tenancy()->initialized) {
            throw new LogicException(
                'Deleting an invoice requires an active tenant.'
            );
        }

        DB::transaction(function () use ($invoice): void {
            $lockedInvoice = Invoice::query()
                ->whereKey($invoice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInvoice->payments()->exists()) {
                throw ValidationException::withMessages([
                    'invoice' => 'Invoices with recorded payments cannot be deleted.',
                ]);
            }

            $tenantId = (string) $lockedInvoice->tenant_id;

            $lockedInvoice->items()->delete();
            $lockedInvoice->delete();

            DB::afterCommit(
                fn () => DashboardCache::invalidate($tenantId)
            );
        });
    }
}
