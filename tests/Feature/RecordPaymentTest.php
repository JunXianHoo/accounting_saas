<?php

use App\Actions\Invoices\RecordPayment;
use App\Models\Invoice;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $company = createTenant(['name' => 'Company A']);
    tenancy()->initialize($company);

    $this->invoice = Invoice::factory()->create([
        'subtotal' => '100.00',
        'total' => '100.00',
        'status' => 'unpaid',
    ]);

    $this->recordPayment = app(RecordPayment::class);
});

afterEach(function () {
    tenancy()->end();
});

test('a partial payment is recorded and marks the invoice partial', function () {
    $payment = $this->recordPayment->handle($this->invoice, '30.00', '2026-11-04');

    expect($payment->amount)->toBe('30.00');
    expect($payment->invoice_id)->toBe($this->invoice->id);
    expect($payment->tenant_id)->toBe($this->invoice->tenant_id);
    expect($this->invoice->refresh()->status)->toBe('partial');
});

test('paying the remaining balance marks the invoice paid', function () {
    $this->recordPayment->handle($this->invoice, '30.00', '2026-11-04');
    $this->recordPayment->handle($this->invoice, '70.00', '2026-11-04');

    expect($this->invoice->refresh()->status)->toBe('paid');
    $this->assertTenantDatabaseCount('payments', 2);
});

test('an overpayment leaves the existing payment and status intact', function () {
    $this->recordPayment->handle($this->invoice, '30.00', '2026-11-04');

    expect(fn () => $this->recordPayment->handle($this->invoice, '70.01', '2026-11-04'))
        ->toThrow(ValidationException::class);

    expect($this->invoice->refresh()->status)->toBe('partial');
    $this->assertTenantDatabaseCount('payments', 1);
});
