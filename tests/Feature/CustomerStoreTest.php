<?php

use App\Models\User;
use Illuminate\Support\Facades\Schema;

afterEach(function () {
    tenancy()->end();
});

test('users can create customers for their company', function () {
    $company = createTenant(['name' => 'Company A']);
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

    $this->assertTenantDatabaseCount('customers', 1);

    $this->assertTenantDatabaseHas('customers', [
        'name' => 'John',
        'email' => 'john@example.com',
        'phone' => '0123456789',
        'address' => 'Kuala Lumpur',
    ]);

    expect(tenancy()->initialized)->toBeFalse();
});

test('customer name and email are required', function () {
    $company = createTenant(['name' => 'Company A']);
    $user = User::factory()->create(['tenant_id' => $company->id]);

    $this->actingAs($user)
        ->post(route('customers.store'), [])
        ->assertSessionHasErrors(['name', 'email']);

    $this->assertTenantDatabaseCount('customers', 0);
});

test('customer email must be valid', function () {
    $company = createTenant(['name' => 'Company A']);
    $user = User::factory()->create(['tenant_id' => $company->id]);

    $this->actingAs($user)
        ->post(route('customers.store'), [
            'name' => 'John',
            'email' => 'not-an-email',
        ])
        ->assertSessionHasErrors(['email']);

    $this->assertTenantDatabaseCount('customers', 0);
});

test('users cannot submit another company ID', function () {
    $companyA = createTenant(['name' => 'Company A']);
    $companyB = createTenant(['name' => 'Company B']);
    $user = User::factory()->create(['tenant_id' => $companyA->id]);

    $this->actingAs($user)
        ->post(route('customers.store'), [
            'name' => 'John',
            'email' => 'john@example.com',
            'tenant_id' => $companyB->id,
        ])
        ->assertSessionHasErrors(['tenant_id']);

    $this->assertTenantDatabaseCount('customers', 0);
});

test('guests cannot create customers', function () {
    $this->post(route('customers.store'), [
        'name' => 'John',
        'email' => 'john@example.com',
    ])->assertRedirect(route('login'));

    expect(Schema::hasTable('customers'))->toBeFalse();
});

test('users without a company cannot create customers', function () {
    $user = User::factory()->create(['tenant_id' => null]);

    $this->actingAs($user)
        ->post(route('customers.store'), [
            'name' => 'John',
            'email' => 'john@example.com',
        ])
        ->assertForbidden();

    expect(Schema::hasTable('customers'))->toBeFalse();
});
