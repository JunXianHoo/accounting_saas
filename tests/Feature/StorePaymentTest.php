<?php

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->company = createTenant(['name' => 'Company A']);
    $this->user = User::factory()->create([
        'tenant_id' => $this->company->id,
    ]);
    $this->submissionId = (string) Str::uuid();
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
            'submission_id' => $this->submissionId,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('invoices.show', $this->invoice));

    $this->assertTenantDatabaseHas('payments', [
        'invoice_id' => $this->invoice->id,
        'amount' => '30.00',
        'reference' => 'BANK-12345',
        'submission_id' => $this->submissionId,
    ]);

    $this->assertTenantDatabaseHas('invoices', [
        'id' => $this->invoice->id,
        'status' => 'partial',
    ]);
});

test('retries of the same payment submission are recorded only once', function (string $amount, string $status) {
    $this->actingAs($this->user);
    $route = route('invoices.payments.store', $this->invoice);
    $payload = [
        'amount' => $amount,
        'paid_at' => today()->toDateString(),
        'submission_id' => $this->submissionId,
    ];

    $this->post($route, $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('invoices.show', $this->invoice));
    $this->post($route, $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('invoices.show', $this->invoice));

    $this->assertTenantDatabaseCount('payments', 1);
    $this->assertTenantDatabaseHas('invoices', ['id' => $this->invoice->id, 'status' => $status]);
})->with([
    'partial payment' => ['30.00', 'partial'],
    'full payment' => ['100.00', 'paid'],
]);

test('separate payment submissions can have identical details', function () {
    $this->actingAs($this->user);
    $route = route('invoices.payments.store', $this->invoice);
    $payload = [
        'amount' => '30.00',
        'paid_at' => today()->toDateString(),
        'reference' => 'BANK-12345',
        'submission_id' => $this->submissionId,
    ];

    $this->post($route, $payload)->assertSessionHasNoErrors();
    $this->post($route, [...$payload, 'submission_id' => (string) Str::uuid()])
        ->assertSessionHasNoErrors();

    $this->assertTenantDatabaseCount('payments', 2);
});

test('a submission ID cannot be reused with different payment details', function () {
    $this->actingAs($this->user);
    $route = route('invoices.payments.store', $this->invoice);
    $payload = [
        'amount' => '30.00',
        'paid_at' => today()->toDateString(),
        'submission_id' => $this->submissionId,
    ];

    $this->post($route, $payload)->assertSessionHasNoErrors();
    $this->post($route, [...$payload, 'amount' => '40.00'])->assertSessionHasErrors('submission_id');

    $this->assertTenantDatabaseCount('payments', 1);
    $this->assertTenantDatabaseHas('payments', ['submission_id' => $this->submissionId, 'amount' => '30.00']);
});

test('a valid payment submission ID is required', function (?string $submissionId) {
    $this->actingAs($this->user)
        ->post(route('invoices.payments.store', $this->invoice), [
            'amount' => '30.00',
            'paid_at' => today()->toDateString(),
            'submission_id' => $submissionId,
        ])
        ->assertSessionHasErrors('submission_id');

    $this->assertTenantDatabaseCount('payments', 0);
})->with(['missing ID' => [null], 'malformed ID' => ['invalid']]);

test('a user can record a payment on the invoice issue date', function () {
    $this->actingAs($this->user)
        ->post(route('invoices.payments.store', $this->invoice), [
            'amount' => '30.00',
            'paid_at' => $this->company->run(fn () => $this->invoice->issue_date->toDateString()),
            'submission_id' => $this->submissionId,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('invoices.show', $this->invoice));

    $this->assertTenantDatabaseCount('payments', 1);
    $this->assertTenantDatabaseHas('payments', [
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
            'submission_id' => $this->submissionId,
        ])
        ->assertSessionHasErrors('reference');

    $this->assertTenantDatabaseCount('payments', 0);
});

test('a user cannot record a payment before the invoice issue date', function () {
    $this->actingAs($this->user)
        ->post(route('invoices.payments.store', $this->invoice), [
            'amount' => '30.00',
            'paid_at' => $this->company->run(fn () => $this->invoice->issue_date->subDay()->toDateString()),
            'submission_id' => $this->submissionId,
        ])
        ->assertSessionHasErrors('paid_at');

    $this->assertTenantDatabaseCount('payments', 0);
    $this->assertTenantDatabaseHas('invoices', [
        'id' => $this->invoice->id,
        'status' => 'unpaid',
    ]);
});

test('a user cannot record a payment for a future date', function () {
    $this->actingAs($this->user)
        ->post(route('invoices.payments.store', $this->invoice), [
            'amount' => '30.00',
            'paid_at' => today()->addDay()->toDateString(),
            'submission_id' => $this->submissionId,
        ])
        ->assertSessionHasErrors('paid_at');

    $this->assertTenantDatabaseCount('payments', 0);
});

test('a user cannot record a payment on another company invoice', function () {
    $otherCompany = createTenant(['name' => 'Company B']);
    $otherInvoice = $otherCompany->run(fn () => Invoice::factory()->create([
        'subtotal' => '100.00',
        'total' => '100.00',
    ]));

    $this->actingAs($this->user)
        ->post(route('invoices.payments.store', $otherInvoice), [
            'amount' => '30.00',
            'paid_at' => today()->toDateString(),
            'submission_id' => $this->submissionId,
        ])
        ->assertNotFound();

    $this->assertTenantDatabaseCount('payments', 0);
});
