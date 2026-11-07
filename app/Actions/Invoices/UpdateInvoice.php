<?php

namespace App\Actions\Invoices;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Support\DashboardCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class UpdateInvoice
{
    /**
     * @param array{
     *     customer_id: int|string,
     *     issue_date: string,
     *     due_date: string,
     *     items: list<array{description: string, quantity: string, unit_price: string}>
     * } $data
     */
    public function handle(Invoice $invoice, array $data): Invoice
    {
        if (! tenancy()->initialized) {
            throw new LogicException(
                'Updating an invoice requires an active tenant.'
            );
        }

        return DB::transaction(function () use ($invoice, $data): Invoice {
            $lockedInvoice = Invoice::query()
                ->whereKey($invoice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInvoice->payments()->exists()) {
                throw ValidationException::withMessages([
                    'invoice' => 'Invoices with recorded payments cannot be edited.',
                ]);
            }

            $customer = Customer::query()
                ->whereKey($data['customer_id'])
                ->lockForUpdate()
                ->first();

            if (! $customer || $customer->status !== 'AC') {
                throw ValidationException::withMessages([
                    'customer_id' => 'Select an active customer from your company.',
                ]);
            }

            $calculation = app(CalculateInvoiceTotals::class)
                ->handle($data['items']);

            $lockedInvoice->fill([
                'customer_id' => $customer->id,
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
            ]);

            $lockedInvoice->subtotal = $calculation['subtotal'];
            $lockedInvoice->total = $calculation['subtotal'];
            $lockedInvoice->status = 'unpaid';
            $lockedInvoice->save();

            $lockedInvoice->items()->delete();

            foreach ($calculation['items'] as $input) {
                $item = new InvoiceItem([
                    'description' => $input['description'],
                    'quantity' => $input['quantity'],
                    'unit_price' => $input['unit_price'],
                ]);

                $item->line_total = $input['line_total'];

                $lockedInvoice->items()->save($item);
            }

            $tenantId = (string) $lockedInvoice->tenant_id;

            DB::afterCommit(
                fn () => DashboardCache::invalidate($tenantId)
            );

            return $lockedInvoice->load(['customer', 'items']);
        });
    }
}
