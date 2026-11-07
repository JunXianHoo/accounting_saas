<?php

use App\Models\User;
use Stancl\Tenancy\Database\Models\Tenant;

afterEach(function () {
    tenancy()->end();
});

test('users can create customers for their company', function () {
    $company = Tenant::create(['name' => 'Company A']);
    $user = User::factory()->create(['tenant_id' => $company->id]);

    $this->actingAs($user)
        ->post(route('customers.store'), [
            'name' => 'John',
            'email' => 'john@example.com',
            'phone' => '0123456789',
            'address' => 'Kuala Lumpur',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('customers.index'));

    $this->assertDatabaseCount('customers', 1);

    $this->assertDatabaseHas('customers', [
        'tenant_id' => $company->id,
        'name' => 'John',
        'email' => 'john@example.com',
        'phone' => '0123456789',
        'address' => 'Kuala Lumpur',
    ]);

    expect(tenancy()->initialized)->toBeFalse();
});

test('customer name and email are required', function () {
    $company = Tenant::create(['name' => 'Company A']);
    $user = User::factory()->create(['tenant_id' => $company->id]);

    $this->actingAs($user)
        ->post(route('customers.store'), [])
        ->assertSessionHasErrors(['name', 'email']);

    $this->assertDatabaseCount('customers', 0);
});

test('customer email must be valid', function () {
    $company = Tenant::create(['name' => 'Company A']);
    $user = User::factory()->create(['tenant_id' => $company->id]);

    $this->actingAs($user)
        ->post(route('customers.store'), [
            'name' => 'John',
            'email' => 'not-an-email',
        ])
        ->assertSessionHasErrors(['email']);

    $this->assertDatabaseCount('customers', 0);
});

test('users cannot submit another company ID', function () {
    $companyA = Tenant::create(['name' => 'Company A']);
    $companyB = Tenant::create(['name' => 'Company B']);
    $user = User::factory()->create(['tenant_id' => $companyA->id]);

    $this->actingAs($user)
        ->post(route('customers.store'), [
            'name' => 'John',
            'email' => 'john@example.com',
            'tenant_id' => $companyB->id,
        ])
        ->assertSessionHasErrors(['tenant_id']);

    $this->assertDatabaseCount('customers', 0);
});

test('guests cannot create customers', function () {
    $this->post(route('customers.store'), [
        'name' => 'John',
        'email' => 'john@example.com',
    ])->assertRedirect(route('login'));

    $this->assertDatabaseCount('customers', 0);
});

test('users without a company cannot create customers', function () {
    $user = User::factory()->create(['tenant_id' => null]);

    $this->actingAs($user)
        ->post(route('customers.store'), [
            'name' => 'John',
            'email' => 'john@example.com',
        ])
        ->assertForbidden();

    $this->assertDatabaseCount('customers', 0);
});
