<?php

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Stancl\Tenancy\Database\Models\Tenant;

beforeEach(function () {
    $this->company = Tenant::create(['name' => 'Company A']);
    $this->user = User::factory()->create([
        'tenant_id' => $this->company->id,
    ]);
});

test('a user can view their company invoice and its items', function () {
    $invoice = $this->company->run(function () {
        $invoice = Invoice::factory()->create([
            'subtotal' => '100.00',
            'total' => '100.00',
        ]);

        InvoiceItem::factory()->for($invoice)->create([
            'description' => 'Consulting',
        ]);

        return $invoice;
    });

    $this->actingAs($this->user)
        ->get(route('invoices.show', $invoice))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('invoices/Show')
            ->where('invoice.id', $invoice->id)
            ->where('invoice.total', '100.00')
            ->has('invoice.items', 1)
            ->where('invoice.items.0.description', 'Consulting')
        );
});

test('a user cannot view another company invoice', function () {
    $otherCompany = Tenant::create(['name' => 'Company B']);
    $invoice = $otherCompany->run(fn () => Invoice::factory()->create());

    $this->actingAs($this->user)
        ->get(route('invoices.show', $invoice))
        ->assertNotFound();
});