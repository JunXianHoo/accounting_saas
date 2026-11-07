<?php

use App\Models\Invoice;
use App\Models\User;
use Stancl\Tenancy\Database\Models\Tenant;

beforeEach(function () {
    $this->company = Tenant::create(['name' => 'Company A']);
    $this->user = User::factory()->create([
        'tenant_id' => $this->company->id,
    ]);
    $this->invoice = $this->company->run(fn () => Invoice::factory()->create([
        'issue_date' => today()->subDays(2)->toDateString(),
        'subtotal' => '100.00',
        'total' => '100.00',
    ]));
});

test('a user can record a payment on their company invoice', function () {
    $this->actingAs($this->user)
        ->post(route('invoices.payments.store', $this->invoice), [
            'amount' => '30.00',
            'paid_at' => today()->toDateString(),
            'reference' => 'BANK-12345',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('invoices.show', $this->invoice));

    $this->assertDatabaseHas('payments', [
        'invoice_id' => $this->invoice->id,
        'tenant_id' => $this->company->id,
        'amount' => '30.00',
        'reference' => 'BANK-12345',
    ]);

    $this->assertDatabaseHas('invoices', [
        'id' => $this->invoice->id,
        'status' => 'partial',
    ]);
});

test('a user can record a payment on the invoice issue date', function () {
    $this->actingAs($this->user)
        ->post(route('invoices.payments.store', $this->invoice), [
            'amount' => '30.00',
            'paid_at' => $this->invoice->issue_date->toDateString(),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('invoices.show', $this->invoice));

    $this->assertDatabaseCount('payments', 1);
    $this->assertDatabaseHas('payments', [
        'invoice_id' => $this->invoice->id,
        'reference' => null,
    ]);
});

test('a payment reference cannot exceed 255 characters', function () {
    $this->actingAs($this->user)
        ->post(route('invoices.payments.store', $this->invoice), [
            'amount' => '30.00',
            'paid_at' => today()->toDateString(),
            'reference' => str_repeat('a', 256),
        ])
        ->assertSessionHasErrors('reference');

    $this->assertDatabaseCount('payments', 0);
});

test('a user cannot record a payment before the invoice issue date', function () {
    $this->actingAs($this->user)
        ->post(route('invoices.payments.store', $this->invoice), [
            'amount' => '30.00',
            'paid_at' => $this->invoice->issue_date->subDay()->toDateString(),
        ])
        ->assertSessionHasErrors('paid_at');

    $this->assertDatabaseCount('payments', 0);
    $this->assertDatabaseHas('invoices', [
        'id' => $this->invoice->id,
        'status' => 'unpaid',
    ]);
});

test('a user cannot record a payment for a future date', function () {
    $this->actingAs($this->user)
        ->post(route('invoices.payments.store', $this->invoice), [
            'amount' => '30.00',
            'paid_at' => today()->addDay()->toDateString(),
        ])
        ->assertSessionHasErrors('paid_at');

    $this->assertDatabaseCount('payments', 0);
});

test('a user cannot record a payment on another company invoice', function () {
    $otherCompany = Tenant::create(['name' => 'Company B']);
    $otherInvoice = $otherCompany->run(fn () => Invoice::factory()->create([
        'subtotal' => '100.00',
        'total' => '100.00',
    ]));

    $this->actingAs($this->user)
        ->post(route('invoices.payments.store', $otherInvoice), [
            'amount' => '30.00',
            'paid_at' => today()->toDateString(),
        ])
        ->assertNotFound();

    $this->assertDatabaseCount('payments', 0);
});
