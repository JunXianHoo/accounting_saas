<?php

namespace App\Jobs;

use App\Mail\InvoiceIssued;
use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Stancl\Tenancy\Database\Models\Tenant;

class SendInvoiceEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 60;

    public function __construct(
        public string $tenantId,
        public int $invoiceId,
    ) {}

    public function handle(): void
    {
        $previousTenant = tenant();
        $company = Tenant::findOrFail($this->tenantId);

        try {
            tenancy()->initialize($company);

            DB::transaction(function () {
                try {
                    $invoice = Invoice::query()
                        ->lockForUpdate()
                        ->findOrFail($this->invoiceId);
                } catch (ModelNotFoundException $exception) {
                    $stillExists = DB::table('invoices')
                        ->where('id', $this->invoiceId)
                        ->exists();

                    if ($stillExists) {
                        throw $exception;
                    }

                    return;
                }
                if ($invoice->emailed_at !== null) {
                    return;
                }

                $invoice->load(['customer', 'items']);

                Mail::to($invoice->customer->email)->send(new InvoiceIssued($invoice));

                $invoice->emailed_at = now();
                $invoice->save();
            });
        } finally {
            if ($previousTenant === null) {
                tenancy()->end();
            } else {
                tenancy()->initialize($previousTenant);
            }
        }
    }
}
