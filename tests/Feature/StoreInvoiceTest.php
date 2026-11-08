<?php

use App\Jobs\SendInvoiceEmail;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Stancl\Tenancy\Database\Models\Tenant;

beforeEach(function () {
    Queue::fake();

    $this->company = Tenant::create(['name' => 'Company A']);
    $this->user = User::factory()->create([
        'tenant_id' => $this->company->id,
    ]);

    $this->customer = $this->company->run(
        fn () => Customer::factory()->create(['status' => 'AC'])
    );

    $this->payload = [
        'customer_id' => $this->customer->id,
        'issue_date' => '2026-11-04',
        'due_date' => '2026-11-18',
        'items' => [
            [
                'description' => 'Consulting',
                'quantity' => '2',
                'unit_price' => '12.50',
            ],
        ],
    ];
});

test('a user can create an invoice for their company customer', function () {
    $this->actingAs($this->user)
        ->post(route('invoices.store'), $this->payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('invoices.index'));

    $this->assertDatabaseHas('invoices', [
        'tenant_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'total' => '25.00',
    ]);
    $this->assertDatabaseCount('invoice_items', 1);

    $invoiceId = $this->company->run(fn () => Invoice::sole()->id);

    Queue::assertPushed(SendInvoiceEmail::class, fn (SendInvoiceEmail $job) => $job->tenantId === $this->company->id
        && $job->invoiceId === $invoiceId
        && $job->afterCommit === true
    );
});

test('a user cannot invoice another company customer', function () {
    $otherCompany = Tenant::create(['name' => 'Company B']);
    $otherCustomer = $otherCompany->run(
        fn () => Customer::factory()->create(['status' => 'AC'])
    );

    $payload = $this->payload;
    $payload['customer_id'] = $otherCustomer->id;

    $this->actingAs($this->user)
        ->post(route('invoices.store'), $payload)
        ->assertSessionHasErrors('customer_id');

    $this->assertDatabaseCount('invoices', 0);
    $this->assertDatabaseCount('invoice_items', 0);

    Queue::assertNothingPushed();
});
