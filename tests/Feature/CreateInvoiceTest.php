<?php

use App\Actions\Invoices\CreateInvoice;
use App\Models\Customer;
use App\Models\InvoiceItem;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Database\Models\Tenant;

beforeEach(function () {
    $this->company = Tenant::create(['name' => 'Company A']);

    tenancy()->initialize($this->company);

    $this->customer = Customer::factory()->create([
        'status' => 'AC',
    ]);

    $this->data = [
        'customer_id' => $this->customer->id,
        'issue_date' => '2026-11-03',
        'due_date' => '2026-12-03',
        'items' => [
            [
                'description' => 'Consulting',
                'quantity' => '1.000',
                'unit_price' => '100.00',
            ],
        ],
    ];
});

afterEach(function () {
    tenancy()->end();
});

test('invoice creation calculates and saves multiple items', function () {
    $this->data['items'] = [
        [
            'description' => 'Consulting',
            'quantity' => '2.000',
            'unit_price' => '50.00',
        ],
        [
            'description' => 'Support',
            'quantity' => '1.500',
            'unit_price' => '19.99',
        ],
    ];

    $invoice = app(CreateInvoice::class)->handle($this->data);

    expect($invoice->tenant_id)->toBe($this->company->id);
    expect($invoice->customer_id)->toBe($this->customer->id);
    expect($invoice->status)->toBe('unpaid');
    expect($invoice->subtotal)->toBe('129.99');
    expect($invoice->total)->toBe('129.99');

    expect($invoice->items)->toHaveCount(2);

    $consulting = $invoice->items->firstWhere('description', 'Consulting');
    $support = $invoice->items->firstWhere('description', 'Support');

    expect($consulting->line_total)->toBe('100.00');
    expect($support->line_total)->toBe('29.99');

    foreach ($invoice->items as $item) {
        expect($item->tenant_id)->toBe($this->company->id);
        expect($item->invoice_id)->toBe($invoice->id);
    }

    $this->assertDatabaseCount('invoices', 1);
    $this->assertDatabaseCount('invoice_items', 2);

    $this->assertDatabaseHas('invoices', [
        'id' => $invoice->id,
        'tenant_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'total' => '129.99',
    ]);
});

test('each line is rounded half up before totals are summed', function () {
    $this->data['items'] = [
        [
            'description' => 'Small item A',
            'quantity' => '0.005',
            'unit_price' => '1.00',
        ],
        [
            'description' => 'Small item B',
            'quantity' => '0.005',
            'unit_price' => '1.00',
        ],
    ];

    $invoice = app(CreateInvoice::class)->handle($this->data);

    expect($invoice->items->pluck('line_total')->all())
        ->toBe(['0.01', '0.01']);

    expect($invoice->subtotal)->toBe('0.02');
    expect($invoice->total)->toBe('0.02');

    $this->assertDatabaseCount('invoices', 1);
    $this->assertDatabaseCount('invoice_items', 2);
});

test('inactive customers cannot receive new invoices', function () {
    $this->customer->status = 'DA';
    $this->customer->save();

    expect(fn () => app(CreateInvoice::class)->handle($this->data))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('invoices', 0);
    $this->assertDatabaseCount('invoice_items', 0);
});

test('invoices cannot be created for another company customer', function () {
    $companyB = Tenant::create(['name' => 'Company B']);

    tenancy()->initialize($companyB);
    $customerB = Customer::factory()->create(['status' => 'AC']);

    tenancy()->initialize($this->company);

    $this->data['customer_id'] = $customerB->id;

    expect(fn () => app(CreateInvoice::class)->handle($this->data))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('invoices', 0);
    $this->assertDatabaseCount('invoice_items', 0);
});

test('line totals exceeding the money column limit are rejected', function () {
    $this->data['items'] = [
        [
            'description' => 'Oversized line',
            'quantity' => '2.000',
            'unit_price' => '999999999999.99',
        ],
    ];

    expect(fn () => app(CreateInvoice::class)->handle($this->data))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('invoices', 0);
    $this->assertDatabaseCount('invoice_items', 0);
});

test('combined totals exceeding the money column limit are rejected', function () {
    $this->data['items'] = [
        [
            'description' => 'Large item A',
            'quantity' => '1.000',
            'unit_price' => '700000000000.00',
        ],
        [
            'description' => 'Large item B',
            'quantity' => '1.000',
            'unit_price' => '700000000000.00',
        ],
    ];

    expect(fn () => app(CreateInvoice::class)->handle($this->data))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('invoices', 0);
    $this->assertDatabaseCount('invoice_items', 0);
});

test('zero-total invoices are rejected', function () {
    $this->data['items'][0]['unit_price'] = '0.00';

    expect(fn () => app(CreateInvoice::class)->handle($this->data))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('invoices', 0);
    $this->assertDatabaseCount('invoice_items', 0);
});

test('invoice and first item roll back when the second item fails', function () {
    $this->data['items'][] = [
        'description' => 'Second item',
        'quantity' => '1.000',
        'unit_price' => '50.00',
    ];

    InvoiceItem::creating(function (InvoiceItem $item): void {
        if ($item->description === 'Second item') {
            $this->assertDatabaseCount('invoices', 1);
            $this->assertDatabaseCount('invoice_items', 1);

            throw new RuntimeException('Simulated item save failure.');
        }
    });

    expect(fn () => app(CreateInvoice::class)->handle($this->data))
        ->toThrow(RuntimeException::class, 'Simulated item save failure.');

    $this->assertDatabaseCount('invoices', 0);
    $this->assertDatabaseCount('invoice_items', 0);
});
