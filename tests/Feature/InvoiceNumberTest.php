<?php

use App\Models\Invoice;
use App\Support\InvoiceNumber;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Database\Models\Tenant;

test('invoice numbers increment separately for each company and issue date', function () {
    $company = Tenant::create(['name' => 'Company A']);
    $otherCompany = Tenant::create(['name' => 'Company B']);

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
    $company = Tenant::create(['name' => 'Company A']);
    InvoiceNumber::next($company->id, '2026-12-28');
    DB::table('invoice_number_sequences')->where('tenant_id', $company->id)
        ->update(['last_number' => 999]);

    $company->run(function () {
        $invoice = Invoice::factory()->create(['issue_date' => '2026-12-28']);
        expect($invoice->invoice_number)->toBe('INV202612281000');
    });
});

test('the migration numbers existing invoices and continues their sequence', function () {
    $company = Tenant::create(['name' => 'Company A']);
    $invoice = $company->run(fn () => Invoice::factory()->create(['issue_date' => '2026-12-28']));
    $migration = require database_path('migrations/2026_11_05_120000_add_invoice_numbers.php');

    $migration->down();
    $migration->up();

    $company->run(function () use ($invoice) {
        expect($invoice->refresh()->invoice_number)->toBe('INV20261228001');
        $next = Invoice::factory()->create(['issue_date' => '2026-12-28']);
        expect($next->invoice_number)->toBe('INV20261228002');
    });
});
