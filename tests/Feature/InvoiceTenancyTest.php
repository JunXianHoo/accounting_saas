<?php

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Stancl\Tenancy\Database\Models\Tenant;

afterEach(function () {
    tenancy()->end();
});

test('invoices and items are isolated between companies', function () {
    $companyA = Tenant::create(['name' => 'Company A']);
    $companyB = Tenant::create(['name' => 'Company B']);

    tenancy()->initialize($companyA);

    $invoiceA = Invoice::factory()->create([
        'subtotal' => '100.00',
        'total' => '100.00',
    ]);

    $itemA = InvoiceItem::factory()->for($invoiceA)->create();

    tenancy()->initialize($companyB);

    $invoiceB = Invoice::factory()->create([
        'subtotal' => '100.00',
        'total' => '100.00',
    ]);

    $itemB = InvoiceItem::factory()->for($invoiceB)->create();

    // Tenant IDs are assigned automatically.
    expect($invoiceA->tenant_id)->toBe($companyA->id);
    expect($itemA->tenant_id)->toBe($companyA->id);
    expect($invoiceB->tenant_id)->toBe($companyB->id);
    expect($itemB->tenant_id)->toBe($companyB->id);

    // Company B only sees its own records.
    expect(Invoice::pluck('id')->all())->toBe([$invoiceB->id]);
    expect(InvoiceItem::pluck('id')->all())->toBe([$itemB->id]);

    expect(Invoice::find($invoiceA->id))->toBeNull();
    expect(InvoiceItem::find($itemA->id))->toBeNull();

    // Relationships resolve within Company B.
    expect($invoiceB->customer->tenant_id)->toBe($companyB->id);
    expect($invoiceB->items()->pluck('id')->all())->toBe([$itemB->id]);
    expect($itemB->invoice->id)->toBe($invoiceB->id);

    // Switch back and check Company A.
    tenancy()->initialize($companyA);

    expect(Invoice::pluck('id')->all())->toBe([$invoiceA->id]);
    expect(InvoiceItem::pluck('id')->all())->toBe([$itemA->id]);

    expect(Invoice::find($invoiceB->id))->toBeNull();
    expect(InvoiceItem::find($itemB->id))->toBeNull();

    expect($invoiceA->customer->tenant_id)->toBe($companyA->id);
    expect($invoiceA->items()->pluck('id')->all())->toBe([$itemA->id]);
    expect($itemA->invoice->id)->toBe($invoiceA->id);
});

test('invoice queries require an active tenant', function () {
    expect(fn () => Invoice::all())
        ->toThrow(
            LogicException::class,
            'Invoice queries require an active tenant.'
        );
});

test('invoice item queries require an active tenant', function () {
    expect(fn () => InvoiceItem::all())
        ->toThrow(
            LogicException::class,
            'Invoice item queries require an active tenant.'
        );
});