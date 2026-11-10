<?php

use App\Models\Customer;
use Illuminate\Support\Facades\Schema;

afterEach(function () {
    tenancy()->end();
});

test('customers are isolated between companies', function () {
    $companyA = createTenant(['name' => 'Company A']);
    $companyB = createTenant(['name' => 'Company B']);

    tenancy()->initialize($companyA);
    $customerA = Customer::factory()->create();

    tenancy()->initialize($companyB);
    $customerB = Customer::factory()->create();

    expect(Schema::hasColumn('customers', 'tenant_id'))->toBeFalse();

    // Company B can only query its own customer.
    expect(Customer::pluck('id')->all())->toBe([$customerB->id]);
    expect(Customer::find($customerA->id))->toBeNull();

    // Company A can only query its own customer.
    tenancy()->initialize($companyA);

    expect(Customer::pluck('id')->all())->toBe([$customerA->id]);
    expect(Customer::find($customerB->id))->toBeNull();
});

test('customer queries require an active tenant', function () {
    expect(fn () => Customer::all())
        ->toThrow(
            InvalidArgumentException::class,
            'Database connection [tenant] not configured.'
        );
});

test('creating customers requires an active tenant', function () {
    expect(fn () => Customer::factory()->create())
        ->toThrow(
            InvalidArgumentException::class,
            'Database connection [tenant] not configured.'
        );
});
