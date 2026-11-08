<?php

use App\Actions\Invoices\CreateInvoice;
use App\Actions\Invoices\RecordPayment;
use App\Actions\Invoices\UpdateInvoice;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
                'description' => 'Original item',
                'quantity' => '1.000',
                'unit_price' => '100.00',
            ],
        ],
    ];

    $this->invoice = app(CreateInvoice::class)->handle($this->data);
});

afterEach(function () {
    tenancy()->end();
});

test('updating an invoice replaces items and recalculates totals', function () {
    $originalNumber = $this->invoice->invoice_number;
    $originalItemId = $this->invoice->items->first()->id;

    $this->data['issue_date'] = '2026-11-05';
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

    $updated = app(UpdateInvoice::class)
        ->handle($this->invoice, $this->data);

    expect($updated->id)->toBe($this->invoice->id);
    expect($updated->tenant_id)->toBe($this->company->id);
    expect($updated->invoice_number)->toBe($originalNumber);
    expect($updated->issue_date->toDateString())->toBe('2026-11-05');
    expect($updated->subtotal)->toBe('129.99');
    expect($updated->total)->toBe('129.99');
    expect($updated->status)->toBe('unpaid');
    expect($updated->items)->toHaveCount(2);

    expect($updated->items->firstWhere('description', 'Consulting')->line_total)
        ->toBe('100.00');

    expect($updated->items->firstWhere('description', 'Support')->line_total)
        ->toBe('29.99');

    $this->assertDatabaseMissing('invoice_items', [
        'id' => $originalItemId,
    ]);

    $this->assertDatabaseCount('invoices', 1);
    $this->assertDatabaseCount('invoice_items', 2);
});

test('invoices with recorded payments cannot be updated', function (
    string $amount,
    string $expectedStatus,
) {
    app(RecordPayment::class)->handle(
        $this->invoice,
        $amount,
        '2026-11-03',
    );

    $this->data['items'][0]['description'] = 'Changed item';
    $this->data['items'][0]['unit_price'] = '200.00';

    expect(fn () => app(UpdateInvoice::class)
        ->handle($this->invoice, $this->data))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseHas('invoices', [
        'id' => $this->invoice->id,
        'total' => '100.00',
        'status' => $expectedStatus,
    ]);

    $this->assertDatabaseHas('invoice_items', [
        'invoice_id' => $this->invoice->id,
        'description' => 'Original item',
        'unit_price' => '100.00',
    ]);

    $this->assertDatabaseCount('invoice_items', 1);
    $this->assertDatabaseCount('payments', 1);
})->with([
    'partially paid' => ['30.00', 'partial'],
    'fully paid' => ['100.00', 'paid'],
]);

test('failed item replacement restores the original invoice and items', function () {
    $originalItemId = $this->invoice->items->first()->id;
    $this->data['due_date'] = '2026-12-10';
    $this->data['items'] = [
        ['description' => 'First replacement', 'quantity' => '1', 'unit_price' => '20.00'],
        ['description' => 'Failing replacement', 'quantity' => '1', 'unit_price' => '30.00'],
    ];

    InvoiceItem::creating(function (InvoiceItem $item): void {
        if ($item->description === 'Failing replacement') {
            throw new RuntimeException('Simulated item save failure.');
        }
    });

    expect(fn () => app(UpdateInvoice::class)->handle($this->invoice, $this->data))
        ->toThrow(RuntimeException::class, 'Simulated item save failure.');

    $original = $this->invoice->fresh();
    expect($original->total)->toBe('100.00');
    expect($original->due_date->toDateString())->toBe('2026-12-03');
    $this->assertDatabaseHas('invoice_items', [
        'id' => $originalItemId,
        'description' => 'Original item',
        'line_total' => '100.00',
    ]);
    $this->assertDatabaseCount('invoice_items', 1);
});

test('another company invoice cannot be updated', function () {
    $otherCompany = Tenant::create(['name' => 'Company B']);

    $otherInvoice = $otherCompany->run(
        fn () => Invoice::factory()->create([
            'subtotal' => '80.00',
            'total' => '80.00',
        ])
    );

    expect(fn () => app(UpdateInvoice::class)
        ->handle($otherInvoice, $this->data))
        ->toThrow(ModelNotFoundException::class);

    $this->assertDatabaseHas('invoices', [
        'id' => $otherInvoice->id,
        'tenant_id' => $otherCompany->id,
        'total' => '80.00',
    ]);

    $this->assertDatabaseHas('invoices', [
        'id' => $this->invoice->id,
        'tenant_id' => $this->company->id,
        'total' => '100.00',
    ]);
});
