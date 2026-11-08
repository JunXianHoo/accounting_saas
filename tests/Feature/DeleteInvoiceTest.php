<?php

use App\Actions\Invoices\CreateInvoice;
use App\Actions\Invoices\DeleteInvoice;
use App\Actions\Invoices\RecordPayment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Stancl\Tenancy\Database\Models\Tenant;

beforeEach(function () {
    $this->company = Tenant::create(['name' => 'Company A']);

    tenancy()->initialize($this->company);

    $customer = Customer::factory()->create(['status' => 'AC']);

    $this->data = [
        'customer_id' => $customer->id,
        'issue_date' => '2026-11-03',
        'due_date' => '2026-12-03',
        'items' => [
            [
                'description' => 'Consulting',
                'quantity' => '1',
                'unit_price' => '100.00',
            ],
        ],
    ];

    $this->invoice = app(CreateInvoice::class)->handle($this->data);
});

afterEach(function () {
    tenancy()->end();
});

test('deleting an invoice removes its items and refreshes the dashboard', function () {
    $user = User::factory()->create([
        'tenant_id' => $this->company->id,
    ]);

    tenancy()->end();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('invoiceCounts.total', 1)
            ->where('invoiceBalances.outstanding', '100.00')
        );

    $this->delete(route('invoices.destroy', $this->invoice))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('reports.sales'));

    $this->assertDatabaseMissing('invoices', [
        'id' => $this->invoice->id,
    ]);

    $this->assertDatabaseMissing('invoice_items', [
        'invoice_id' => $this->invoice->id,
    ]);

    $this->assertDatabaseHas('customers', [
        'id' => $this->invoice->customer_id,
    ]);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('invoiceCounts.total', 0)
            ->where('invoiceBalances.outstanding', '0.00')
        );

    expect(tenancy()->initialized)->toBeFalse();
});

test('an invoice without payments can be deleted with its items', function () {
    $invoiceId = $this->invoice->id;
    $itemId = $this->invoice->items->first()->id;

    app(DeleteInvoice::class)->handle($this->invoice);

    $this->assertDatabaseMissing('invoices', [
        'id' => $invoiceId,
    ]);

    $this->assertDatabaseMissing('invoice_items', [
        'id' => $itemId,
    ]);

    $this->assertDatabaseHas('customers', [
        'id' => $this->data['customer_id'],
    ]);

    $replacement = app(CreateInvoice::class)->handle($this->data);

    expect($replacement->invoice_number)->toBe('INV20261103002');
});

test('invoices with payments cannot be deleted', function (
    string $amount,
    string $expectedStatus,
) {
    app(RecordPayment::class)->handle(
        $this->invoice,
        $amount,
        '2026-11-03',
    );

    expect(fn () => app(DeleteInvoice::class)
        ->handle($this->invoice))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseHas('invoices', [
        'id' => $this->invoice->id,
        'total' => '100.00',
        'status' => $expectedStatus,
    ]);

    $this->assertDatabaseHas('invoice_items', [
        'invoice_id' => $this->invoice->id,
        'description' => 'Consulting',
    ]);

    $this->assertDatabaseHas('payments', [
        'invoice_id' => $this->invoice->id,
        'amount' => $amount,
    ]);

    $this->assertDatabaseCount('invoices', 1);
    $this->assertDatabaseCount('invoice_items', 1);
    $this->assertDatabaseCount('payments', 1);
})->with([
    'partially paid' => ['30.00', 'partial'],
    'fully paid' => ['100.00', 'paid'],
]);

test('another company invoice cannot be deleted', function () {
    $otherCompany = Tenant::create(['name' => 'Company B']);

    $otherInvoice = $otherCompany->run(function () {
        $invoice = Invoice::factory()->create([
            'subtotal' => '80.00',
            'total' => '80.00',
        ]);

        InvoiceItem::factory()->for($invoice)->create([
            'description' => 'Company B item',
        ]);

        return $invoice;
    });

    expect(fn () => app(DeleteInvoice::class)
        ->handle($otherInvoice))
        ->toThrow(ModelNotFoundException::class);

    $this->assertDatabaseHas('invoices', [
        'id' => $otherInvoice->id,
        'tenant_id' => $otherCompany->id,
    ]);

    $this->assertDatabaseHas('invoice_items', [
        'invoice_id' => $otherInvoice->id,
        'description' => 'Company B item',
    ]);

    $this->assertDatabaseHas('invoices', [
        'id' => $this->invoice->id,
        'tenant_id' => $this->company->id,
    ]);
});

test('the delete route rejects invoices with payments', function (
    string $amount,
    string $expectedStatus,
) {
    app(RecordPayment::class)->handle(
        $this->invoice,
        $amount,
        '2026-11-03',
    );

    $user = User::factory()->create([
        'tenant_id' => $this->company->id,
    ]);

    tenancy()->end();

    $this->actingAs($user)
        ->from(route('invoices.show', $this->invoice))
        ->delete(route('invoices.destroy', $this->invoice))
        ->assertSessionHasErrors('invoice')
        ->assertRedirect(route('invoices.show', $this->invoice));

    $this->assertDatabaseHas('invoices', [
        'id' => $this->invoice->id,
        'status' => $expectedStatus,
        'total' => '100.00',
    ]);

    $this->assertDatabaseHas('invoice_items', [
        'invoice_id' => $this->invoice->id,
        'description' => 'Consulting',
    ]);

    $this->assertDatabaseHas('payments', [
        'invoice_id' => $this->invoice->id,
        'amount' => $amount,
    ]);

    expect(tenancy()->initialized)->toBeFalse();
})->with([
    'partially paid' => ['30.00', 'partial'],
    'fully paid' => ['100.00', 'paid'],
]);

test('the delete route rejects another company invoice', function () {
    $otherCompany = Tenant::create(['name' => 'Company B']);

    $otherInvoice = $otherCompany->run(function () {
        $invoice = Invoice::factory()->create([
            'subtotal' => '80.00',
            'total' => '80.00',
        ]);

        InvoiceItem::factory()->for($invoice)->create([
            'description' => 'Company B item',
        ]);

        return $invoice;
    });

    $user = User::factory()->create([
        'tenant_id' => $this->company->id,
    ]);

    tenancy()->end();

    $this->actingAs($user)
        ->delete(route('invoices.destroy', $otherInvoice))
        ->assertNotFound();

    $this->assertDatabaseHas('invoices', [
        'id' => $otherInvoice->id,
        'tenant_id' => $otherCompany->id,
    ]);

    $this->assertDatabaseHas('invoice_items', [
        'invoice_id' => $otherInvoice->id,
        'description' => 'Company B item',
    ]);

    $this->assertDatabaseHas('invoices', [
        'id' => $this->invoice->id,
        'tenant_id' => $this->company->id,
    ]);

    expect(tenancy()->initialized)->toBeFalse();
});
