<?php

use App\Actions\Invoices\CreateInvoice;
use App\Actions\Invoices\RecordPayment;
use App\Models\Customer;
use App\Models\Payment;
use App\Support\DashboardCache;

use App\Models\User;
use Stancl\Tenancy\Database\Models\Tenant;
use App\Models\Invoice;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('users with a company can visit the dashboard', function () {
    $tenant = Tenant::create([
        'name' => 'Company A',
    ]);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    expect(tenancy()->initialized)->toBeFalse();
});

test('users without a company cannot visit the dashboard', function () {
    $user = User::factory()->create([
        'tenant_id' => null,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertForbidden();
});

test('dashboard counts only invoices for the signed-in company', function () {
    $company = Tenant::create(['name' => 'Company A']);
    $otherCompany = Tenant::create(['name' => 'Company B']);
    $user = User::factory()->create(['tenant_id' => $company->id]);

    $company->run(function () {
        Invoice::factory()->create([
            'status' => 'unpaid',
            'due_date' => today()->toDateString(),
        ]);

        Invoice::factory()->create([
            'status' => 'partial',
            'issue_date' => today()->subDays(2)->toDateString(),
            'due_date' => today()->subDay()->toDateString(),
        ]);

        Invoice::factory()->create(['status' => 'paid']);
    });

    $otherCompany->run(fn () => Invoice::factory()->create([
        'status' => 'unpaid',
    ]));

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('invoiceCounts.total', 3)
            ->where('invoiceCounts.unpaid', 1)
            ->where('invoiceCounts.partial', 1)
            ->where('invoiceCounts.paid', 1)
            ->where('invoiceCounts.overdue', 1)
        );
});

test('dashboard shows payment totals and balances for the signed-in company', function () {
    $company = Tenant::create(['name' => 'Company A']);
    $otherCompany = Tenant::create(['name' => 'Company B']);
    $user = User::factory()->create(['tenant_id' => $company->id]);

    $company->run(function () {
        $overdue = Invoice::factory()->create([
            'issue_date' => today()->subDays(2)->toDateString(),
            'due_date' => today()->subDay()->toDateString(),
            'subtotal' => '100.00',
            'total' => '100.00',
            'status' => 'partial',
        ]);
        $overdue->payments()->create([
            'amount' => '30.00',
            'paid_at' => today()->toDateString(),
        ]);

        Invoice::factory()->create([
            'due_date' => today()->toDateString(),
            'subtotal' => '50.00',
            'total' => '50.00',
            'status' => 'unpaid',
        ]);

        $lastMonth = today()->startOfMonth()->subDay()->toDateString();
        $paid = Invoice::factory()->create([
            'issue_date' => $lastMonth,
            'due_date' => $lastMonth,
            'subtotal' => '80.00',
            'total' => '80.00',
            'status' => 'paid',
        ]);
        $paid->payments()->create([
            'amount' => '80.00',
            'paid_at' => $lastMonth,
        ]);
    });

    $otherCompany->run(function () {
        $invoice = Invoice::factory()->create([
            'subtotal' => '40.00',
            'total' => '40.00',
            'status' => 'paid',
        ]);
        $invoice->payments()->create([
            'amount' => '40.00',
            'paid_at' => today()->toDateString(),
        ]);
    });

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('receivedThisMonth', '30.00')
            ->where('invoiceBalances.outstanding', '120.00')
            ->where('invoiceBalances.overdue', '70.00')
            ->where('invoiceBalances.paid', '80.00')
        );
});


test('committed invoices and payments refresh the cached dashboard', function () {
    $company = Tenant::create(['name' => 'Company A']);

    $company->run(function () use ($company) {
        $date = today()->toDateString();
        $loads = 0;

        $load = function () use (&$loads) {
            $loads++;

            return [Invoice::count(), Payment::count()];
        };
        $summary = fn () => DashboardCache::remember($company->id, $date, $load);

        expect($summary())->toBe([0, 0]);
        expect($summary())->toBe([0, 0]);
        expect($loads)->toBe(1);

        $customer = Customer::factory()->create(['status' => 'AC']);
        $invoice = app(CreateInvoice::class)->handle([
            'customer_id' => $customer->id,
            'issue_date' => $date,
            'due_date' => $date,
            'items' => [[
                'description' => 'Consulting',
                'quantity' => '1',
                'unit_price' => '50.00',
            ]],
        ]);

        expect($summary())->toBe([1, 0]);

        app(RecordPayment::class)->handle($invoice, '20.00', $date);

        expect($summary())->toBe([1, 1]);
        expect($loads)->toBe(3);
    });
});

test('changing a payment date refreshes the dashboard cache', function () {
    $company = Tenant::create(['name' => 'Company A']);

    $company->run(function () use ($company) {
        $lastMonth = today()->startOfMonth()->subDay()->toDateString();
        $invoice = Invoice::factory()->create([
            'issue_date' => $lastMonth,
            'due_date' => today()->toDateString(),
            'subtotal' => '100.00',
            'total' => '100.00',
            'status' => 'partial',
        ]);
        $payment = $invoice->payments()->create([
            'amount' => '30.00',
            'paid_at' => today()->toDateString(),
        ]);

        $loads = 0;
        $load = function () use (&$loads) {
            $loads++;

            $received = Payment::query()
                ->where('paid_at', '>=', today()->startOfMonth()->toDateString())
                ->where('paid_at', '<', today()->addDay()->toDateString())
                ->sum('amount');

            return bcadd((string) $received, '0', 2);
        };
        $summary = fn () => DashboardCache::remember($company->id, today()->toDateString(), $load);

        expect($summary())->toBe('30.00');
        expect($summary())->toBe('30.00');
        expect($loads)->toBe(1);

        $payment->update(['paid_at' => $lastMonth]);

        expect($summary())->toBe('0.00');
        expect($loads)->toBe(2);
    });
});
