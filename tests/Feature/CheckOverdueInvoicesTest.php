<?php

use App\Models\Invoice;
use Illuminate\Support\Facades\Artisan;

test('the overdue command checks each company separately', function () {
    $company = createTenant(['name' => 'Company A']);
    $otherCompany = createTenant(['name' => 'Company B']);

    [$partialId, $paidId] = $company->run(function () {
        $pastInvoice = [
            'issue_date' => today()->subDays(2)->toDateString(),
            'due_date' => today()->subDay()->toDateString(),
            'subtotal' => '100.00',
            'total' => '100.00',
        ];

        $partial = Invoice::factory()->create([
            ...$pastInvoice,
            'status' => 'partial',
        ]);

        $partial->payments()->create([
            'amount' => '30.00',
            'paid_at' => today()->toDateString(),
        ]);

        $paid = Invoice::factory()->create([
            ...$pastInvoice,
            'status' => 'paid',
        ]);

        $paid->payments()->create([
            'amount' => '100.00',
            'paid_at' => today()->toDateString(),
        ]);

        return [$partial->id, $paid->id];
    });

    $otherCompany->run(fn () => Invoice::factory()->create([
        'status' => 'unpaid',
        'due_date' => today()->toDateString(),
    ]));

    expect(Artisan::call('invoices:check-overdue'))->toBe(0);

    expect(Artisan::output())
        ->toContain("Company {$company->id} — 1 overdue invoice(s)")
        ->toContain("Company {$otherCompany->id} — 0 overdue invoice(s)");

    expect(tenancy()->initialized)->toBeFalse();

    $firstDetection = $company->run(function () use ($partialId, $paidId) {
        $partial = Invoice::findOrFail($partialId);
        expect($partial->status)->toBe('partial')
            ->and($partial->overdue_detected_at)->not->toBeNull()
            ->and(Invoice::findOrFail($paidId)->overdue_detected_at)->toBeNull();

        return $partial->overdue_detected_at;
    });

    $otherCompany->run(fn () => expect(Invoice::firstOrFail()->overdue_detected_at)->toBeNull());

    $this->travel(1)->day();
    expect(Artisan::call('invoices:check-overdue'))->toBe(0);
    $company->run(fn () => expect(Invoice::findOrFail($partialId)->overdue_detected_at?->equalTo($firstDetection))->toBeTrue());
});

test('fully settled invoices are excluded even when their status is outdated', function () {
    $company = createTenant(['name' => 'Company A']);

    $company->run(function () {
        $invoice = Invoice::factory()->create([
            'issue_date' => today()->subDays(2)->toDateString(),
            'due_date' => today()->subDay()->toDateString(),
            'subtotal' => '0.80',
            'total' => '0.80',
            'status' => 'partial',
        ]);

        $invoice->payments()->createMany([
            ['amount' => '0.10', 'paid_at' => today()->toDateString()],
            ['amount' => '0.70', 'paid_at' => today()->toDateString()],
        ]);
    });

    expect(Artisan::call('invoices:check-overdue'))->toBe(0);

    expect(Artisan::output())
        ->toContain("Company {$company->id} — 0 overdue invoice(s)");

    expect(tenancy()->initialized)->toBeFalse();
});
