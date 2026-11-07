<?php

use App\Models\Customer;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Stancl\Tenancy\Database\Models\Tenant;

beforeEach(function () {
    $this->companyA = Tenant::create(['name' => 'Company A']);
    $this->companyB = Tenant::create(['name' => 'Company B']);

    $user = User::factory()->create([
        'tenant_id' => $this->companyA->id,
    ]);

    tenancy()->initialize($this->companyA);
    $this->customerA = Customer::factory()->create([
        'name' => 'Original A',
        'email' => 'a@example.com',
    ]);

    tenancy()->initialize($this->companyB);
    $this->customerB = Customer::factory()->create([
        'name' => 'Original B',
        'email' => 'b@example.com',
    ]);

    tenancy()->end();

    $this->actingAs($user);
});

afterEach(function () {
    tenancy()->end();
});

test('users can open their own customer edit form', function () {
    $this->get(route('customers.edit', $this->customerA->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/Edit')
            ->where('customer.id', $this->customerA->id)
            ->where('customer.name', 'Original A')
        );
});

test('users can update their own customer', function () {
    $this->patch(route('customers.update', $this->customerA->id), [
        'name' => 'Updated A',
        'email' => 'updated@example.com',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('customers.index'));

    $this->assertDatabaseHas('customers', [
        'id' => $this->customerA->id,
        'tenant_id' => $this->companyA->id,
        'name' => 'Updated A',
        'email' => 'updated@example.com',
    ]);

    expect(tenancy()->initialized)->toBeFalse();
});

test('invalid updates leave the customer unchanged', function () {
    $this->patch(route('customers.update', $this->customerA->id), [
        'name' => 'Changed name',
        'email' => 'invalid-email',
    ])->assertSessionHasErrors(['email']);

    $this->assertDatabaseHas('customers', [
        'id' => $this->customerA->id,
        'name' => 'Original A',
        'email' => 'a@example.com',
    ]);
});

test('users cannot move customers to another company', function () {
    $this->patch(route('customers.update', $this->customerA->id), [
        'name' => 'Changed name',
        'email' => 'changed@example.com',
        'tenant_id' => $this->companyB->id,
    ])->assertSessionHasErrors(['tenant_id']);

    $this->assertDatabaseHas('customers', [
        'id' => $this->customerA->id,
        'tenant_id' => $this->companyA->id,
        'name' => 'Original A',
        'email' => 'a@example.com',
    ]);
});

test('users cannot open another company customer edit form', function () {
    $this->get(route('customers.edit', $this->customerB->id))
        ->assertNotFound();

    expect(tenancy()->initialized)->toBeFalse();
});

test('users cannot update another company customer', function () {
    $this->patch(route('customers.update', $this->customerB->id), [
        'name' => 'Unauthorized change',
        'email' => 'changed@example.com',
    ])->assertNotFound();

    $this->assertDatabaseHas('customers', [
        'id' => $this->customerB->id,
        'tenant_id' => $this->companyB->id,
        'name' => 'Original B',
        'email' => 'b@example.com',
    ]);

    expect(tenancy()->initialized)->toBeFalse();
});