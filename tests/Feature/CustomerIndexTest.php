<?php

use App\Models\Customer;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Stancl\Tenancy\Database\Models\Tenant;

afterEach(function () {
    tenancy()->end();
});

test('guests cannot access the customer list', function () {
    $this->get(route('customers.index'))
        ->assertRedirect(route('login'));
});

test('users without a company cannot access the customer list', function () {
    $user = User::factory()->create([
        'tenant_id' => null,
    ]);

    $this->actingAs($user)
        ->get(route('customers.index'))
        ->assertForbidden();
});

test('users only receive their own company customers', function () {
    $companyA = Tenant::create(['name' => 'Company A']);
    $companyB = Tenant::create(['name' => 'Company B']);

    $alice = User::factory()->create([
        'tenant_id' => $companyA->id,
    ]);

    $bob = User::factory()->create([
        'tenant_id' => $companyB->id,
    ]);

    tenancy()->initialize($companyA);
    $customerA = Customer::factory()->create();

    tenancy()->initialize($companyB);
    $customerB = Customer::factory()->create();

    // Clear setup context so the middleware must identify the company.
    tenancy()->end();

    $this->actingAs($alice)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/Index')
            ->has('customers.data', 1)
            ->where('customers.data.0.id', $customerA->id)
            ->where('customers.total', 1)
        );

    expect(tenancy()->initialized)->toBeFalse();

    $this->actingAs($bob)
        ->get(route('customers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/Index')
            ->has('customers.data', 1)
            ->where('customers.data.0.id', $customerB->id)
            ->where('customers.total', 1)
        );

    expect(tenancy()->initialized)->toBeFalse();
});