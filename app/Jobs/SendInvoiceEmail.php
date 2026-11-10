<?php

namespace App\Jobs;

use App\Mail\InvoiceIssued;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class SendInvoiceEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 60;

    public function __construct(
        public string $tenantId,
        public string $invoiceId,
    ) {}

    public function handle(): void
    {
        $previousTenant = tenant();
        $company = Tenant::findOrFail($this->tenantId);

        try {
            tenancy()->initialize($company);

            $useResend = config('services.resend.invoice_delivery');

            if ($useResend) {
                $this->prepareResendDelivery();
            }

            DB::transaction(function () use ($useResend): void {
                $invoice = $this->pendingInvoiceForUpdate();

                if ($invoice === null) {
                    return;
                }

                if ($useResend) {
                    $this->sendViaResend($invoice);
                } else {
                    if ($invoice->email_payload !== null) {
                        throw new RuntimeException('Enable Resend to retry this invoice email.');
                    }

                    $invoice->load(['customer', 'items']);
                    Mail::mailer('log')->to($invoice->customer->email)->send(new InvoiceIssued($invoice));
                }

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

    private function pendingInvoiceForUpdate(): ?Invoice
    {
        $invoice = Invoice::query()->lockForUpdate()->find($this->invoiceId);

        return $invoice?->emailed_at === null ? $invoice : null;
    }

    private function prepareResendDelivery(): void
    {
        // Commit the payload before the first HTTP request so it survives a failed send attempt.
        DB::transaction(function (): void {
            $invoice = $this->pendingInvoiceForUpdate();

            if ($invoice === null || $invoice->email_payload !== null) {
                return;
            }

            $invoice->load(['customer', 'items']);
            $mail = new InvoiceIssued($invoice);

            $invoice->email_payload = [
                'from' => config('mail.from.address'),
                'to' => [$invoice->customer->email],
                'subject' => $mail->envelope()->subject,
                'text' => $mail->render(),
            ];
            $invoice->email_first_attempt_at = now();
            $invoice->save();
        });
    }

    private function sendViaResend(Invoice $invoice): void
    {
        $firstAttempt = $invoice->email_first_attempt_at;

        // Leave a margin inside Resend's 24-hour deduplication window.
        if ($firstAttempt === null || $firstAttempt->addHours(23)->lte(now())) {
            throw new RuntimeException('Invoice email retry window expired. Check the Resend logs before resending.');
        }

        $key = config('services.resend.key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('Set RESEND_API_KEY to enable invoice email delivery.');
        }

        $emailId = Http::withToken($key)
            ->withHeader('Idempotency-Key', "invoice-issued/{$this->tenantId}/{$this->invoiceId}")
            ->timeout(15)
            ->post('https://api.resend.com/emails', $invoice->email_payload)
            ->throw()
            ->json('id');

        if (! is_string($emailId) || $emailId === '') {
            throw new RuntimeException('Resend did not return an email ID.');
        }
    }
}
