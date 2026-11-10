<?php

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

afterEach(function () {
    tenancy()->end();
});

test('frontend build assets use public URLs in tenant context', function () {
    $company = createTenant(['name' => 'Company A']);
    $publicAsset = asset('build/assets/app.js');

    expect($company->run(fn () => asset('build/assets/app.js')))->toBe($publicAsset);
});

test('invoices and items are isolated between companies', function () {
    $companyA = createTenant(['name' => 'Company A']);
    $companyB = createTenant(['name' => 'Company B']);

    expect(Schema::hasTable('invoices'))->toBeFalse();

    tenancy()->initialize($companyA);
    $databaseA = DB::connection()->getDatabaseName();

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

    expect(DB::connection()->getDatabaseName())->not->toBe($databaseA);
    expect(Schema::hasColumn('invoices', 'tenant_id'))->toBeFalse();
    expect(Schema::hasColumn('invoice_items', 'tenant_id'))->toBeFalse();
    expect($invoiceB->invoice_number)->toBe($invoiceA->invoice_number);
    expect($itemB->id)->toBe($itemA->id);

    // Company B only sees its own records.
    expect(Invoice::pluck('id')->all())->toBe([$invoiceB->id]);
    expect(InvoiceItem::pluck('id')->all())->toBe([$itemB->id]);

    expect(Invoice::find($invoiceA->id))->toBeNull();

    // Relationships resolve within Company B.
    expect($invoiceB->customer->id)->toBe($invoiceB->customer_id);
    expect($invoiceB->items()->pluck('id')->all())->toBe([$itemB->id]);
    expect($itemB->invoice->id)->toBe($invoiceB->id);

    // Switch back and check Company A.
    tenancy()->initialize($companyA);

    expect(Invoice::pluck('id')->all())->toBe([$invoiceA->id]);
    expect(InvoiceItem::pluck('id')->all())->toBe([$itemA->id]);

    expect(Invoice::find($invoiceB->id))->toBeNull();

    expect($invoiceA->customer->id)->toBe($invoiceA->customer_id);
    expect($invoiceA->items()->pluck('id')->all())->toBe([$itemA->id]);
    expect($itemA->invoice->id)->toBe($invoiceA->id);
});

test('invoice queries require an active tenant', function () {
    expect(fn () => Invoice::all())
        ->toThrow(
            InvalidArgumentException::class,
            'Database connection [tenant] not configured.'
        );
});

test('invoice item queries require an active tenant', function () {
    expect(fn () => InvoiceItem::all())
        ->toThrow(
            InvalidArgumentException::class,
            'Database connection [tenant] not configured.'
        );
});
