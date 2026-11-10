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
        'status' => 'DA',
    ]);

    tenancy()->initialize($this->companyB);
    $this->customerB = Customer::factory()->create([
        'status' => 'DA',
    ]);

    tenancy()->end();
});

afterEach(function () {
    tenancy()->end();
});

test('users can reactivate their own company customer', function () {
    $this->actingAs($this->user)
        ->patch(route('customers.reactivate', $this->customerA->id))
        ->assertRedirect(route('customers.index'));

    $this->assertTenantDatabaseHas('customers', [
        'id' => $this->customerA->id,
        'name' => $this->customerA->name,
        'email' => $this->customerA->email,
        'status' => 'AC',
    ], $this->companyA);

    $this->assertTenantDatabaseHas('customers', [
        'id' => $this->customerB->id,
        'status' => 'DA',
    ], $this->companyB);

    $this->assertTenantDatabaseCount('customers', 1, $this->companyA);
    $this->assertTenantDatabaseCount('customers', 1, $this->companyB);

    expect(tenancy()->initialized)->toBeFalse();
});

test('users cannot reactivate another company customer', function () {
    $this->actingAs($this->user)
        ->patch(route('customers.reactivate', $this->customerB->id))
        ->assertNotFound();

    $this->assertTenantDatabaseHas('customers', [
        'id' => $this->customerB->id,
        'status' => 'DA',
    ], $this->companyB);

    $this->assertTenantDatabaseCount('customers', 1, $this->companyA);
    $this->assertTenantDatabaseCount('customers', 1, $this->companyB);

    expect(tenancy()->initialized)->toBeFalse();
});

test('guests cannot reactivate customers', function () {
    $this->patch(route('customers.reactivate', $this->customerA->id))
        ->assertRedirect(route('login'));

    $this->assertTenantDatabaseHas('customers', [
        'id' => $this->customerA->id,
        'status' => 'DA',
    ], $this->companyA);
});

test('users without a company cannot reactivate customers', function () {
    $user = User::factory()->create(['tenant_id' => null]);

    $this->actingAs($user)
        ->patch(route('customers.reactivate', $this->customerA->id))
        ->assertForbidden();

    $this->assertTenantDatabaseHas('customers', [
        'id' => $this->customerA->id,
        'status' => 'DA',
    ], $this->companyA);
});
