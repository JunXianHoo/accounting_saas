<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Stancl\Tenancy\Database\Models\Tenant;

beforeEach(function () {
    $this->company = Tenant::create(['name' => 'Company A']);
    $this->user = User::factory()->create([
        'tenant_id' => $this->company->id,
    ]);
});

test('the former invoice list redirects to invoice reports', function () {
    $this->actingAs($this->user)
        ->get(route('invoices.index'))
        ->assertRedirect(route('reports.sales'));
});

test('sales includes only company invoices issued in the selected range', function () {
    $from = today()->subDays(7)->toDateString();
    $to = today()->toDateString();
    $beforeRange = today()->subDays(8)->toDateString();

    $this->company->run(function () use ($from, $to, $beforeRange) {
        foreach ([$from => '100.00', $to => '50.00', $beforeRange => '200.00'] as $date => $total) {
            Invoice::factory()->create([
                'issue_date' => $date,
                'subtotal' => $total,
                'total' => $total,
            ]);
        }
    });

    $otherCompany = Tenant::create(['name' => 'Company B']);
    $otherCompany->run(fn () => Invoice::factory()->create([
        'issue_date' => $to,
        'subtotal' => '300.00',
        'total' => '300.00',
    ]));

    $this->actingAs($this->user)
        ->get(route('reports.sales', ['date_range' => 1, 'from' => $from, 'to' => $to]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Sales')
            ->where('filters.from', $from)
            ->where('filters.to', $to)
            ->where('salesTotal', '150.00')
            ->has('invoices.data', 2)
        );
});

test('invoice report combines customer and status filters across all dates', function () {
    [$customer, $matchingInvoice] = $this->company->run(function () {
        $customer = Customer::factory()->create(['name' => 'Example customer']);
        $sameNameCustomer = Customer::factory()->create(['name' => $customer->name]);
        $attributes = [
            'issue_date' => today()->startOfMonth()->subDays(2)->toDateString(),
            'due_date' => today()->startOfMonth()->subDay()->toDateString(),
            'status' => 'partial',
            'subtotal' => '100.00',
            'total' => '100.00',
        ];

        $matchingInvoice = Invoice::factory()->for($customer)->create($attributes);
        $matchingInvoice->payments()->create([
            'amount' => '30.00',
            'paid_at' => today()->toDateString(),
        ]);

        Invoice::factory()->for($sameNameCustomer)->create($attributes);
        Invoice::factory()->for($customer)->create([
            ...$attributes,
            'status' => 'unpaid',
        ]);

        return [$customer, $matchingInvoice];
    });

    $this->actingAs($this->user)
        ->get(route('reports.sales', [
            'from' => today()->toDateString(),
            'to' => today()->toDateString(),
            'status' => 'partial',
            'customer_id' => $customer->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/Sales')
            ->where('salesTotal', '100.00')
            ->where('paidTotal', '30.00')
            ->where('outstandingTotal', '70.00')
            ->has('invoices.data', 1)
            ->where('invoices.data.0.id', $matchingInvoice->id)
            ->where('invoices.data.0.outstanding_balance', '70.00')
        );
});

test('invoice number filtering matches only the signed-in company and can be disabled', function () {
    $date = today()->toDateString();
    $invoice = $this->company->run(function () use ($date) {
        $invoice = Invoice::factory()->create(['issue_date' => $date, 'total' => '100.00']);
        Invoice::factory()->create(['issue_date' => $date, 'total' => '50.00']);

        return $invoice;
    });

    $otherCompany = Tenant::create(['name' => 'Company B']);
    $otherCompany->run(fn () => Invoice::factory()->create(['issue_date' => $date, 'total' => '900.00']));

    $query = [
        'filter_by_invoice_number' => 1,
        'invoice_number' => $invoice->invoice_number,
        'status' => 'unpaid',
        'date_range' => 1,
        'from' => $date,
        'to' => $date,
    ];

    $this->actingAs($this->user)
        ->get(route('reports.sales', $query))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('invoices.data', 1)
            ->where('invoices.data.0.id', $invoice->id)
            ->where('salesTotal', '100.00')
            ->where('filters.invoice_number', $invoice->invoice_number)
        );

    $this->get(route('reports.sales', [...$query, 'filter_by_invoice_number' => 0]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('invoices.data', 2)
            ->where('salesTotal', '150.00')
        );
});

test('invoice number filtering requires a number when enabled', function () {
    $this->actingAs($this->user)
        ->get(route('reports.sales', ['filter_by_invoice_number' => 1]))
        ->assertSessionHasErrors('invoice_number');
});

test('outstanding reports exclude fully settled decimal amounts', function () {
    $outstanding = $this->company->run(function () {
        $settled = Invoice::factory()->create(['total' => '0.80', 'status' => 'paid']);
        $settled->payments()->createMany([
            ['amount' => '0.10', 'paid_at' => today()->toDateString()],
            ['amount' => '0.70', 'paid_at' => today()->toDateString()],
        ]);

        $outstanding = Invoice::factory()->create(['total' => '0.80', 'status' => 'partial']);
        $outstanding->payments()->create([
            'amount' => '0.79',
            'paid_at' => today()->toDateString(),
        ]);

        return $outstanding;
    });

    $this->actingAs($this->user)
        ->get(route('reports.sales', ['balance' => 'outstanding']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('invoices.data', 1)
            ->where('invoices.data.0.id', $outstanding->id)
            ->where('outstandingTotal', '0.01')
        );
});

test('the outstanding report opens the shared report across all dates', function () {
    $this->actingAs($this->user)
        ->get(route('reports.outstanding'))
        ->assertRedirect(route('reports.sales', ['balance' => 'outstanding']));
});

test('sales rejects an end date in the future', function () {
    $this->actingAs($this->user)
        ->get(route('reports.sales', [
            'date_range' => 1,
            'from' => today()->toDateString(),
            'to' => today()->addDay()->toDateString(),
        ]))
        ->assertSessionHasErrors('to');
});

test('balance filters use payments and apply the due date only for overdue', function () {
    $lateInvoice = $this->company->run(function () {
        $pastDates = [
            'issue_date' => today()->subDays(2)->toDateString(),
            'due_date' => today()->subDay()->toDateString(),
        ];

        $late = Invoice::factory()->create([
            ...$pastDates,
            'status' => 'partial',
            'total' => '100.00',
        ]);
        $late->payments()->create([
            'amount' => '30.00',
            'paid_at' => today()->toDateString(),
        ]);

        $settled = Invoice::factory()->create([
            ...$pastDates,
            'status' => 'paid',
            'total' => '50.00',
        ]);
        $settled->payments()->create([
            'amount' => '50.00',
            'paid_at' => today()->toDateString(),
        ]);

        Invoice::factory()->create([
            'due_date' => today()->toDateString(),
            'status' => 'unpaid',
            'total' => '20.00',
        ]);

        return $late;
    });

    $this->actingAs($this->user)
        ->get(route('reports.sales', ['balance' => 'outstanding']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('outstandingTotal', '90.00')
            ->has('invoices.data', 2)
        );

    $this->get(route('reports.sales', ['balance' => 'overdue']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('outstandingTotal', '70.00')
            ->has('invoices.data', 1)
            ->where('invoices.data.0.id', $lateInvoice->id)
        );
});
