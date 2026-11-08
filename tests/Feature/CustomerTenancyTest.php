<?php

use App\Models\Customer;
use Stancl\Tenancy\Database\Models\Tenant;

afterEach(function () {
    tenancy()->end();
});

test('customers are isolated between companies', function () {
    $companyA = Tenant::create(['name' => 'Company A']);
    $companyB = Tenant::create(['name' => 'Company B']);

    tenancy()->initialize($companyA);
    $customerA = Customer::factory()->create();

    tenancy()->initialize($companyB);
    $customerB = Customer::factory()->create();

    // Company IDs are assigned automatically.
    expect($customerA->tenant_id)->toBe($companyA->id);
    expect($customerB->tenant_id)->toBe($companyB->id);

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
            LogicException::class,
            'Customer queries require an active tenant.'
        );
});

test('creating customers requires an active tenant', function () {
    expect(fn () => Customer::factory()->create())
        ->toThrow(
            LogicException::class,
            'Creating a customer requires an active tenant.'
        );
});

test('customers cannot be created for another company', function () {
    $companyA = Tenant::create(['name' => 'Company A']);
    $companyB = Tenant::create(['name' => 'Company B']);

    tenancy()->initialize($companyA);

    expect(fn () => Customer::factory()->create([
        'tenant_id' => $companyB->id,
    ]))->toThrow(
        LogicException::class,
        'The customer must belong to the active tenant.'
    );
});
