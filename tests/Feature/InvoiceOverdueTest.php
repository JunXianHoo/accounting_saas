<?php

use App\Models\Invoice;
use App\Actions\Invoices\CreateInvoice;
use App\Actions\Invoices\RecordPayment;
use App\Models\Customer;
use Stancl\Tenancy\Database\Models\Tenant;

test('overdue depends on the due date and payment status', function (
    string $status,
    int $daysFromToday,
    bool $expected,
) {
    $invoice = new Invoice([
        'due_date' => today()->addDays($daysFromToday)->toDateString(),
    ]);
    $invoice->status = $status;

    expect($invoice->isOverdue())->toBe($expected);
})->with([
    'unpaid and due yesterday' => ['unpaid', -1, true],
    'partially paid and due yesterday' => ['partial', -1, true],
    'unpaid and due today' => ['unpaid', 0, false],
    'unpaid and due tomorrow' => ['unpaid', 1, false],
    'paid and due yesterday' => ['paid', -1, false],
]);

test('an invoice becomes overdue and stops being overdue after full payment', function () {
    $company = Tenant::create(['name' => 'Company A']);

    $company->run(function () {
        $customer = Customer::factory()->create(['status' => 'AC']);

        $invoice = app(CreateInvoice::class)->handle([
            'customer_id' => $customer->id,
            'issue_date' => today()->toDateString(),
            'due_date' => today()->toDateString(),
            'items' => [
                [
                    'description' => 'Consulting',
                    'quantity' => '1',
                    'unit_price' => '100.00',
                ],
            ],
        ]);

        expect($invoice->isOverdue())->toBeFalse();

        $this->travel(1)->days();

        expect($invoice->isOverdue())->toBeTrue();

        app(RecordPayment::class)->handle(
            $invoice,
            '30.00',
            today()->toDateString(),
        );

        $invoice->refresh();

        expect($invoice->status)->toBe('partial');
        expect($invoice->isOverdue())->toBeTrue();

        app(RecordPayment::class)->handle(
            $invoice,
            '70.00',
            today()->toDateString(),
        );

        $invoice->refresh();

        expect($invoice->status)->toBe('paid');
        expect($invoice->isOverdue())->toBeFalse();
    });

    $this->travelBack();

    expect(tenancy()->initialized)->toBeFalse();
});
