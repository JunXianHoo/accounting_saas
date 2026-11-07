<?php

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Database\Models\Tenant;

beforeEach(function () {
    $this->companyA = Tenant::create(['name' => 'Company A']);
    $this->companyB = Tenant::create(['name' => 'Company B']);

    tenancy()->initialize($this->companyA);
    $this->invoiceA = Invoice::factory()->create();

    tenancy()->initialize($this->companyB);
    $this->invoiceB = Invoice::factory()->create();

    tenancy()->end();
});

afterEach(function () {
    tenancy()->end();
});

test('creating invoices requires an active tenant', function () {
    expect(fn () => Invoice::factory()->create([
        'customer_id' => $this->invoiceA->customer_id,
    ]))->toThrow(
        LogicException::class,
        'Creating an invoice requires an active tenant.'
    );

    $this->assertDatabaseCount('invoices', 2);
});

test('creating invoice items requires an active tenant', function () {
    expect(fn () => InvoiceItem::factory()
        ->for($this->invoiceA)
        ->create()
    )->toThrow(
        LogicException::class,
        'Creating an invoice item requires an active tenant.'
    );

    $this->assertDatabaseCount('invoice_items', 0);
});

test('invoices cannot be assigned another company tenant ID', function () {
    tenancy()->initialize($this->companyA);

    expect(fn () => Invoice::factory()->create([
        'tenant_id' => $this->companyB->id,
        'customer_id' => $this->invoiceA->customer_id,
    ]))->toThrow(
        LogicException::class,
        'The invoice must belong to the active tenant.'
    );

    $this->assertDatabaseCount('invoices', 2);
});

test('items cannot be assigned another company tenant ID', function () {
    tenancy()->initialize($this->companyA);

    expect(fn () => InvoiceItem::factory()
        ->for($this->invoiceA)
        ->create(['tenant_id' => $this->companyB->id])
    )->toThrow(
        LogicException::class,
        'The invoice item must belong to the active tenant.'
    );

    $this->assertDatabaseCount('invoice_items', 0);
});

test('invoices cannot reference another company customer', function () {
    tenancy()->initialize($this->companyA);

    expect(fn () => DB::transaction(
        fn () => Invoice::factory()->create([
            'customer_id' => $this->invoiceB->customer_id,
        ])
    ))->toThrow(QueryException::class);

    $this->assertDatabaseCount('invoices', 2);
});

test('items cannot reference another company invoice', function () {
    tenancy()->initialize($this->companyA);

    expect(fn () => DB::transaction(
        fn () => InvoiceItem::factory()
            ->for($this->invoiceB)
            ->create()
    ))->toThrow(QueryException::class);

    $this->assertDatabaseCount('invoice_items', 0);
});