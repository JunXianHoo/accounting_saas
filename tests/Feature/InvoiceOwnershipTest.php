<?php

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->companyA = createTenant(['name' => 'Company A']);
    $this->companyB = createTenant(['name' => 'Company B']);

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
        InvalidArgumentException::class,
        'Database connection [tenant] not configured.'
    );

    $this->assertTenantDatabaseCount('invoices', 1, $this->companyA);
});

test('creating invoice items requires an active tenant', function () {
    expect(fn () => InvoiceItem::factory()
        ->for($this->invoiceA)
        ->create()
    )->toThrow(
        InvalidArgumentException::class,
        'Database connection [tenant] not configured.'
    );

    $this->assertTenantDatabaseCount('invoice_items', 0, $this->companyA);
});

test('invoices cannot reference another company customer', function () {
    tenancy()->initialize($this->companyA);

    expect(fn () => DB::transaction(
        fn () => Invoice::factory()->create([
            'customer_id' => $this->invoiceB->customer_id,
        ])
    ))->toThrow(QueryException::class);

    $this->assertTenantDatabaseCount('invoices', 1, $this->companyA);
});

test('items cannot reference another company invoice', function () {
    tenancy()->initialize($this->companyA);

    expect(fn () => DB::transaction(
        fn () => InvoiceItem::factory()
            ->for($this->invoiceB)
            ->create()
    ))->toThrow(QueryException::class);

    $this->assertTenantDatabaseCount('invoice_items', 0, $this->companyA);
});
