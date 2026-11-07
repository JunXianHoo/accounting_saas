<?php

use App\Models\Customer;
use App\Models\User;
use Stancl\Tenancy\Database\Models\Tenant;

beforeEach(function () {
    $companyA = Tenant::create(['name' => 'Company A']);
    $companyB = Tenant::create(['name' => 'Company B']);

    $this->user = User::factory()->create([
        'tenant_id' => $companyA->id,
    ]);

    tenancy()->initialize($companyA);
    $this->customerA = Customer::factory()->create([
        'status' => 'DA',
    ]);

    tenancy()->initialize($companyB);
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

    $this->assertDatabaseHas('customers', [
        'id' => $this->customerA->id,
        'tenant_id' => $this->customerA->tenant_id,
        'name' => $this->customerA->name,
        'email' => $this->customerA->email,
        'status' => 'AC',
    ]);

    $this->assertDatabaseHas('customers', [
        'id' => $this->customerB->id,
        'status' => 'DA',
    ]);

    $this->assertDatabaseCount('customers', 2);

    expect(tenancy()->initialized)->toBeFalse();
});

test('users cannot reactivate another company customer', function () {
    $this->actingAs($this->user)
        ->patch(route('customers.reactivate', $this->customerB->id))
        ->assertNotFound();

    $this->assertDatabaseHas('customers', [
        'id' => $this->customerB->id,
        'status' => 'DA',
    ]);

    $this->assertDatabaseCount('customers', 2);

    expect(tenancy()->initialized)->toBeFalse();
});

test('guests cannot reactivate customers', function () {
    $this->patch(route('customers.reactivate', $this->customerA->id))
        ->assertRedirect(route('login'));

    $this->assertDatabaseHas('customers', [
        'id' => $this->customerA->id,
        'status' => 'DA',
    ]);
});

test('users without a company cannot reactivate customers', function () {
    $user = User::factory()->create(['tenant_id' => null]);

    $this->actingAs($user)
        ->patch(route('customers.reactivate', $this->customerA->id))
        ->assertForbidden();

    $this->assertDatabaseHas('customers', [
        'id' => $this->customerA->id,
        'status' => 'DA',
    ]);
});