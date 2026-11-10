<?php

use App\Models\Invoice;
use App\Support\InvoiceNumber;
use Illuminate\Support\Facades\DB;

test('invoice numbers increment separately for each company and issue date', function () {
    $company = createTenant(['name' => 'Company A']);
    $otherCompany = createTenant(['name' => 'Company B']);

    $company->run(function () {
        $first = Invoice::factory()->create(['issue_date' => '2026-12-28']);
        $second = Invoice::factory()->create(['issue_date' => '2026-12-28']);
        $nextDay = Invoice::factory()->create(['issue_date' => '2026-12-29']);

        expect($first->invoice_number)->toBe('INV20261228001');
        expect($second->invoice_number)->toBe('INV20261228002');
        expect($nextDay->invoice_number)->toBe('INV20261229001');

        $second->delete();
        $replacement = Invoice::factory()->create(['issue_date' => '2026-12-28']);
        expect($replacement->invoice_number)->toBe('INV20261228003');
    });

    $otherCompany->run(function () {
        $invoice = Invoice::factory()->create(['issue_date' => '2026-12-28']);
        expect($invoice->invoice_number)->toBe('INV20261228001');
    });
});

test('invoice numbers support more than 999 invoices per date', function () {
    $company = createTenant(['name' => 'Company A']);
    $company->run(function () {
        InvoiceNumber::next('2026-12-28');
        DB::table('invoice_number_sequences')->update(['last_number' => 999]);

        $invoice = Invoice::factory()->create(['issue_date' => '2026-12-28']);
        expect($invoice->invoice_number)->toBe('INV202612281000');
    });
});
