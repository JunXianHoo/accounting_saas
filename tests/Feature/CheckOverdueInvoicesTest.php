<?php

use App\Models\Invoice;
use Illuminate\Support\Facades\Artisan;
use Stancl\Tenancy\Database\Models\Tenant;

test('the overdue command checks each company separately', function () {
    $company = Tenant::create(['name' => 'Company A']);
    $otherCompany = Tenant::create(['name' => 'Company B']);

    $company->run(function () {
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
});

test('fully settled invoices are excluded even when their status is outdated', function () {
    $company = Tenant::create(['name' => 'Company A']);

    $company->run(function () {
        $invoice = Invoice::factory()->create([
            'issue_date' => today()->subDays(2)->toDateString(),
            'due_date' => today()->subDay()->toDateString(),
            'subtotal' => '100.00',
            'total' => '100.00',
            'status' => 'partial',
        ]);

        $invoice->payments()->create([
            'amount' => '100.00',
            'paid_at' => today()->toDateString(),
        ]);
    });

    expect(Artisan::call('invoices:check-overdue'))->toBe(0);

    expect(Artisan::output())
        ->toContain("Company {$company->id} — 0 overdue invoice(s)");

    expect(tenancy()->initialized)->toBeFalse();
});
