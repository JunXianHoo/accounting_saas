<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;

test('users can delete an unused customer in their company only', function () {
    $company = createTenant(['name' => 'Company A']);
    $otherCompany = createTenant(['name' => 'Company B']);
    $user = User::factory()->for($company)->create();
    $customer = $company->run(fn () => Customer::factory()->create());
    $otherCustomer = $otherCompany->run(fn () => Customer::factory()->create());

    $this->actingAs($user)->delete(route('customers.destroy', $otherCustomer))->assertNotFound();
    $this->delete(route('customers.destroy', $customer))->assertRedirect(route('customers.index'));

    $company->run(fn () => expect(Customer::find($customer->id))->toBeNull());
    $otherCompany->run(fn () => expect(Customer::find($otherCustomer->id))->not->toBeNull());
});

test('customers with invoice history can only be deactivated', function () {
    $company = createTenant(['name' => 'Company A']);
    $user = User::factory()->for($company)->create();
    $invoice = $company->run(fn () => Invoice::factory()->create());

    $this->actingAs($user)
        ->from(route('customers.index'))
        ->delete(route('customers.destroy', $invoice->customer_id))
        ->assertRedirect(route('customers.index'))
        ->assertSessionHasErrors('customer');

    $company->run(fn () => expect(Invoice::findOrFail($invoice->id)->customer)->not->toBeNull());
});
