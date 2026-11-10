<?php

use App\Actions\Invoices\DeleteInvoice;
use App\Jobs\SendInvoiceEmail;
use App\Mail\InvoiceIssued;
use App\Models\Invoice;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

test('the invoice email job sends once and records the send time', function () {
    Mail::fake();

    $company = createTenant(['name' => 'Company A']);
    [$invoice, $email] = $company->run(function () {
        $invoice = Invoice::factory()->create();

        return [$invoice, $invoice->customer->email];
    });

    $job = new SendInvoiceEmail($company->id, $invoice->id);
    $job->handle();
    $job->handle();

    Mail::assertSent(InvoiceIssued::class, fn (InvoiceIssued $mail) => $mail->hasTo($email));
    Mail::assertSentCount(1);

    $emailedAt = $company->run(fn () => Invoice::findOrFail($invoice->id)->emailed_at);

    expect($emailedAt)->not->toBeNull();
    expect(tenancy()->initialized)->toBeFalse();
});

test('the invoice email job cannot send another company invoice', function () {
    Mail::fake();

    $company = createTenant(['name' => 'Company A']);
    $otherCompany = createTenant(['name' => 'Company B']);
    $invoice = $otherCompany->run(fn () => Invoice::factory()->create());

    $job = new SendInvoiceEmail($company->id, $invoice->id);

    $job->handle();

    Mail::assertNothingSent();
    expect(tenancy()->initialized)->toBeFalse();
});

test('the email job skips an invoice deleted before processing', function () {
    Mail::fake();

    $company = createTenant(['name' => 'Company A']);

    $invoice = $company->run(
        fn () => Invoice::factory()->create()
    );

    $job = new SendInvoiceEmail($company->id, $invoice->id);

    $company->run(
        fn () => app(DeleteInvoice::class)->handle($invoice)
    );

    $job->handle();

    Mail::assertNothingSent();

    expect(tenancy()->initialized)->toBeFalse();
});

test('a resend retry reuses the original request after saving the send time fails', function () {
    config([
        'services.resend.invoice_delivery' => true,
        'services.resend.key' => 'test-key',
    ]);
    Http::preventStrayRequests();
    Http::fake(['https://api.resend.com/emails' => Http::response(['id' => 'test-email-id'])]);

    $company = createTenant(['name' => 'Company A']);
    $invoice = $company->run(fn () => Invoice::factory()->create());
    $failOnce = true;

    Invoice::updating(function (Invoice $invoice) use (&$failOnce): void {
        if ($failOnce && $invoice->isDirty('emailed_at')) {
            $failOnce = false;

            throw new RuntimeException('Could not save the send time.');
        }
    });

    $job = new SendInvoiceEmail($company->id, $invoice->id);
    expect(fn () => $job->handle())->toThrow(RuntimeException::class, 'Could not save the send time.');

    $company->run(function () use ($invoice): void {
        $invoice->refresh();
        expect($invoice->emailed_at)->toBeNull();
        expect($invoice->email_payload)->not->toBeNull();
        expect($invoice->toArray())->not->toHaveKey('email_payload');
        $invoice->customer->update(['email' => 'changed@example.test']);
    });

    $job->handle();
    $job->handle();

    Http::assertSentCount(2);
    [$firstRequest, $retriedRequest] = Http::recorded()->map(fn (array $exchange) => $exchange[0])->all();
    expect($firstRequest->header('Idempotency-Key'))->toBe(["invoice-issued/{$company->id}/{$invoice->id}"]);
    expect($retriedRequest->header('Idempotency-Key'))->toBe($firstRequest->header('Idempotency-Key'));
    expect($retriedRequest->data())->toBe($firstRequest->data());
    expect($company->run(fn () => $invoice->fresh()->emailed_at))->not->toBeNull();
    expect(tenancy()->initialized)->toBeFalse();
});

test('a resend retry stops before the provider deduplication window expires', function () {
    config([
        'services.resend.invoice_delivery' => true,
        'services.resend.key' => 'test-key',
    ]);
    Http::preventStrayRequests();
    Http::fake(['https://api.resend.com/emails' => Http::response(status: 503)]);

    $company = createTenant(['name' => 'Company A']);
    $invoice = $company->run(fn () => Invoice::factory()->create());
    $job = new SendInvoiceEmail($company->id, $invoice->id);

    expect(fn () => $job->handle())->toThrow(RequestException::class);

    $this->travel(23)->hours();

    expect(fn () => $job->handle())->toThrow(RuntimeException::class, 'Invoice email retry window expired.');
    Http::assertSentCount(1);
    expect($company->run(fn () => $invoice->fresh()->emailed_at))->toBeNull();
});
