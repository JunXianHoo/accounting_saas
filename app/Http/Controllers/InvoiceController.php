<?php

namespace App\Http\Controllers;

use App\Actions\Invoices\CreateInvoice;
use App\Actions\Invoices\DeleteInvoice;
use App\Actions\Invoices\RecordPayment;
use App\Actions\Invoices\UpdateInvoice;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Jobs\SendInvoiceEmail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function store(
        StoreInvoiceRequest $request,
        CreateInvoice $createInvoice,
    ): RedirectResponse {
        $invoice = $createInvoice->handle($request->invoiceData());

        SendInvoiceEmail::dispatch(
            (string) $invoice->tenant_id,
            $invoice->id,
        )->afterCommit();

        return to_route('reports.sales');
    }

    public function create(): Response
    {
        return Inertia::render('invoices/Create', [
            'customers' => Customer::query()
                ->where('status', 'AC')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function show(Invoice $invoice): Response
    {
        $invoice->load([
            'customer:id,name',
            'items',
            'payments' => fn ($query) => $query
                ->orderByDesc('paid_at')
                ->orderByDesc('id'),
        ]);

        $totalPaid = '0.00';

        foreach ($invoice->payments as $payment) {
            $totalPaid = bcadd($totalPaid, $payment->amount, 2);
        }

        return Inertia::render('invoices/Show', [
            'invoice' => $invoice->append('overdue'),
            'totalPaid' => $totalPaid,
            'outstandingBalance' => bcsub($invoice->total, $totalPaid, 2),
            'latestPaymentDate' => today()->toDateString(),
        ]);
    }

    public function recordPayment(
        StorePaymentRequest $request,
        Invoice $invoice,
        RecordPayment $recordPayment,
    ): RedirectResponse {
        $data = $request->validated();

        $recordPayment->handle($invoice, $data['amount'], $data['paid_at'], $data['reference'] ?? null);

        return to_route('invoices.show', $invoice);
    }

    public function edit(Invoice $invoice): Response
    {
        abort_if($invoice->payments()->exists(), 403);

        $invoice->load('items');

        return Inertia::render('invoices/Edit', [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'customer_id' => $invoice->customer_id,
                'issue_date' => $invoice->issue_date->toDateString(),
                'due_date' => $invoice->due_date->toDateString(),
                'items' => $invoice->items->map(
                    fn (InvoiceItem $item) => $item->only([
                        'description',
                        'quantity',
                        'unit_price',
                    ])
                ),
            ],
            'customers' => Customer::query()
                ->where('status', 'AC')
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name']),
        ]);
    }

    public function update(
        UpdateInvoiceRequest $request,
        Invoice $invoice,
        UpdateInvoice $updateInvoice,
    ): RedirectResponse {
        $updateInvoice->handle($invoice, $request->invoiceData());

        return to_route('invoices.show', $invoice);
    }

    public function destroy(
        Invoice $invoice,
        DeleteInvoice $deleteInvoice,
    ): RedirectResponse {
        $deleteInvoice->handle($invoice);

        return to_route('reports.sales');
    }
}
