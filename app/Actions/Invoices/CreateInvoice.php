<?php

namespace App\Actions\Invoices;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Support\DashboardCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class CreateInvoice
{
    /**
     * Create an invoice from validated input.
     *
     * @param array{
     *     customer_id: string,
     *     issue_date: string,
     *     due_date: string,
     *     items: list<array{
     *         description: string,
     *         quantity: string,
     *         unit_price: string
     *     }>
     * } $data
     */
    public function handle(array $data): Invoice
    {
        if (! tenancy()->initialized) {
            throw new LogicException(
                'Creating an invoice requires an active tenant.'
            );
        }

        return DB::transaction(function () use ($data): Invoice {
            $customer = Customer::query()
                ->whereKey($data['customer_id'])
                ->lockForUpdate()
                ->first();

            if (! $customer || $customer->status !== 'AC') {
                throw ValidationException::withMessages([
                    'customer_id' => 'Select an active customer from your company.',
                ]);
            }

            $calculation = app(CalculateInvoiceTotals::class)->handle($data['items']);
            $subtotal = $calculation['subtotal'];
            $calculatedItems = $calculation['items'];

            $invoice = new Invoice([
                'customer_id' => $customer->id,
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
            ]);

            $invoice->subtotal = $subtotal;
            $invoice->total = $subtotal;
            $invoice->status = 'unpaid';
            $invoice->save();

            foreach ($calculatedItems as $input) {
                $item = new InvoiceItem([
                    'description' => $input['description'],
                    'quantity' => $input['quantity'],
                    'unit_price' => $input['unit_price'],
                ]);

                $item->line_total = $input['line_total'];

                $invoice->items()->save($item);
            }

            $tenantId = (string) tenant()->getTenantKey();
            DB::afterCommit(fn () => DashboardCache::invalidate($tenantId));

            return $invoice->load(['customer', 'items']);
        });
    }
}
