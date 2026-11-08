<?php

use App\Actions\Invoices\DeleteInvoice;
use App\Jobs\SendInvoiceEmail;
use App\Mail\InvoiceIssued;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Mail;
use Stancl\Tenancy\Database\Models\Tenant;

test('the invoice email job sends once and records the send time', function () {
    Mail::fake();

    $company = Tenant::create(['name' => 'Company A']);
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

    $company = Tenant::create(['name' => 'Company A']);
    $otherCompany = Tenant::create(['name' => 'Company B']);
    $invoice = $otherCompany->run(fn () => Invoice::factory()->create());

    $job = new SendInvoiceEmail($company->id, $invoice->id);

    expect(fn () => $job->handle())->toThrow(ModelNotFoundException::class);

    Mail::assertNothingSent();
    expect(tenancy()->initialized)->toBeFalse();
});

test('the email job skips an invoice deleted before processing', function () {
    Mail::fake();

    $company = Tenant::create(['name' => 'Company A']);

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
