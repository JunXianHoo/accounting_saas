<?php

use App\Models\Customer;
use App\Models\User;

beforeEach(function () {
    $this->companyA = createTenant(['name' => 'Company A']);
    $this->companyB = createTenant(['name' => 'Company B']);

    $this->user = User::factory()->create([
        'tenant_id' => $this->companyA->id,
    ]);

    tenancy()->initialize($this->companyA);
    $this->customerA = Customer::factory()->create([
        'status' => 'AC',
    ]);

    tenancy()->initialize($this->companyB);
    $this->customerB = Customer::factory()->create([
        'status' => 'AC',
    ]);

    tenancy()->end();
});

afterEach(function () {
    tenancy()->end();
});

test('users can deactivate their own company customer', function () {
    $this->actingAs($this->user)
        ->patch(route('customers.deactivate', $this->customerA->id))
        ->assertRedirect(route('customers.index'));

    $this->assertTenantDatabaseHas('customers', [
        'id' => $this->customerA->id,
        'name' => $this->customerA->name,
        'email' => $this->customerA->email,
        'status' => 'DA',
    ], $this->companyA);

    $this->assertTenantDatabaseHas('customers', [
        'id' => $this->customerB->id,
        'status' => 'AC',
    ], $this->companyB);

    $this->assertTenantDatabaseCount('customers', 1, $this->companyA);
    $this->assertTenantDatabaseCount('customers', 1, $this->companyB);

    expect(tenancy()->initialized)->toBeFalse();
});

test('users cannot deactivate another company customer', function () {
    $this->actingAs($this->user)
        ->patch(route('customers.deactivate', $this->customerB->id))
        ->assertNotFound();

    $this->assertTenantDatabaseHas('customers', [
        'id' => $this->customerB->id,
        'status' => 'AC',
    ], $this->companyB);

    $this->assertTenantDatabaseCount('customers', 1, $this->companyA);
    $this->assertTenantDatabaseCount('customers', 1, $this->companyB);

    expect(tenancy()->initialized)->toBeFalse();
});

test('guests cannot deactivate customers', function () {
    $this->patch(route('customers.deactivate', $this->customerA->id))
        ->assertRedirect(route('login'));

    $this->assertTenantDatabaseHas('customers', [
        'id' => $this->customerA->id,
        'status' => 'AC',
    ], $this->companyA);
});

test('users without a company cannot deactivate customers', function () {
    $user = User::factory()->create(['tenant_id' => null]);

    $this->actingAs($user)
        ->patch(route('customers.deactivate', $this->customerA->id))
        ->assertForbidden();

    $this->assertTenantDatabaseHas('customers', [
        'id' => $this->customerA->id,
        'status' => 'AC',
    ], $this->companyA);
});
