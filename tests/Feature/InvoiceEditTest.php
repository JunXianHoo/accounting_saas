<?php

use App\Actions\Invoices\CreateInvoice;
use App\Actions\Invoices\RecordPayment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Stancl\Tenancy\Database\Models\Tenant;

beforeEach(function () {
    $this->company = Tenant::create(['name' => 'Company A']);
    $this->user = User::factory()->create(['tenant_id' => $this->company->id]);

    $this->invoice = $this->company->run(function () {
        $customer = Customer::factory()->create(['status' => 'AC']);

        return app(CreateInvoice::class)->handle([
            'customer_id' => $customer->id,
            'issue_date' => '2026-11-03',
            'due_date' => '2026-12-03',
            'items' => [
                ['description' => 'Original item', 'quantity' => '1', 'unit_price' => '100.00'],
            ],
        ]);
    });

    $this->payload = [
        'customer_id' => $this->invoice->customer_id,
        'issue_date' => '2026-11-03',
        'due_date' => '2026-12-10',
        'items' => [
            ['description' => 'Updated item', 'quantity' => '2', 'unit_price' => '12.50'],
        ],
    ];
});

test('the edit page supplies the company invoice and its items', function () {
    $this->actingAs($this->user)
        ->get(route('invoices.edit', $this->invoice))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('invoices/Edit')
            ->where('invoice.id', $this->invoice->id)
            ->where('invoice.invoice_number', $this->invoice->invoice_number)
            ->where('invoice.issue_date', '2026-11-03')
            ->where('invoice.items.0.description', 'Original item')
            ->has('customers', 1)
            ->where('customers.0.id', $this->invoice->customer_id)
        );

    expect(tenancy()->initialized)->toBeFalse();
});

test('updating through the route saves changes and refreshes the dashboard', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('invoiceBalances.outstanding', '100.00')
        );

    $this->patch(route('invoices.update', $this->invoice), $this->payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('invoices.show', $this->invoice));

    $this->assertDatabaseHas('invoices', [
        'id' => $this->invoice->id,
        'tenant_id' => $this->company->id,
        'invoice_number' => $this->invoice->invoice_number,
        'total' => '25.00',
    ]);
    $this->assertDatabaseHas('invoice_items', [
        'invoice_id' => $this->invoice->id,
        'description' => 'Updated item',
        'line_total' => '25.00',
    ]);
    $this->assertDatabaseMissing('invoice_items', [
        'invoice_id' => $this->invoice->id,
        'description' => 'Original item',
    ]);
    $this->company->run(function () {
        expect($this->invoice->fresh()->due_date->toDateString())->toBe('2026-12-10');
    });

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('invoiceBalances.outstanding', '25.00')
        );

    expect(tenancy()->initialized)->toBeFalse();
});

test('edit and update routes reject another company invoice', function () {
    $otherCompany = Tenant::create(['name' => 'Company B']);
    $otherInvoice = $otherCompany->run(fn () => Invoice::factory()->create(['total' => '80.00']));

    $this->actingAs($this->user)
        ->get(route('invoices.edit', $otherInvoice))
        ->assertNotFound();

    $this->patch(route('invoices.update', $otherInvoice), $this->payload)
        ->assertNotFound();

    $this->assertDatabaseHas('invoices', [
        'id' => $otherInvoice->id,
        'tenant_id' => $otherCompany->id,
        'total' => '80.00',
    ]);

    expect(tenancy()->initialized)->toBeFalse();
});

test('edit and update routes reject invoices with payments', function (string $amount) {
    $this->company->run(fn () => app(RecordPayment::class)
        ->handle($this->invoice, $amount, '2026-11-03'));

    $this->actingAs($this->user)
        ->get(route('invoices.edit', $this->invoice))
        ->assertForbidden();

    $this->patch(route('invoices.update', $this->invoice), $this->payload)
        ->assertForbidden();

    $this->assertDatabaseHas('invoices', [
        'id' => $this->invoice->id,
        'total' => '100.00',
    ]);
    $this->assertDatabaseCount('payments', 1);

    expect(tenancy()->initialized)->toBeFalse();
})->with(['partial' => '30.00', 'paid' => '100.00']);

test('an invoice cannot be reassigned to another company customer', function () {
    $otherCompany = Tenant::create(['name' => 'Company B']);
    $otherCustomer = $otherCompany->run(fn () => Customer::factory()->create(['status' => 'AC']));
    $this->payload['customer_id'] = $otherCustomer->id;

    $this->actingAs($this->user)
        ->patch(route('invoices.update', $this->invoice), $this->payload)
        ->assertSessionHasErrors('customer_id');

    $this->assertDatabaseHas('invoices', [
        'id' => $this->invoice->id,
        'customer_id' => $this->invoice->customer_id,
        'total' => '100.00',
    ]);
});

test('invoice updates reject protected fields and invalid input', function (string $field, mixed $value) {
    data_set($this->payload, $field, $value);

    $this->actingAs($this->user)
        ->patch(route('invoices.update', $this->invoice), $this->payload)
        ->assertSessionHasErrors($field);

    $this->assertDatabaseHas('invoices', [
        'id' => $this->invoice->id,
        'invoice_number' => $this->invoice->invoice_number,
        'total' => '100.00',
    ]);
    $this->assertDatabaseHas('invoice_items', [
        'invoice_id' => $this->invoice->id,
        'description' => 'Original item',
    ]);
})->with([
    'invoice number' => ['invoice_number', 'INV20261103999'],
    'tenant' => ['tenant_id', 'another-company'],
    'total' => ['total', '0.01'],
    'status' => ['status', 'paid'],
    'due date before issue date' => ['due_date', '2026-11-02'],
    'zero quantity' => ['items.0.quantity', '0'],
]);
