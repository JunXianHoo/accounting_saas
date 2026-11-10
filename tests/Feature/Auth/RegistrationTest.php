<?php

use App\Actions\Tenants\ProvisionTenant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'test@example.com')->firstOrFail();

    expect($user->tenant_id)->not->toBeNull();
    expect($user->tenant)->not->toBeNull();
    expect($user->tenant->name)->toBe("Test User's company");

    $this->assertDatabaseHas('tenants', [
        'id' => $user->tenant_id,
    ]);

    $this->assertDatabaseCount('tenants', 1);

    expect(Schema::hasTable('invoices'))->toBeFalse();
    $user->tenant->run(function () {
        expect(Schema::hasTable('invoices'))->toBeTrue();
        expect(Schema::hasTable('users'))->toBeFalse();
    });
});

test('a failed owner save removes the new company database', function () {
    $existing = User::factory()->create();
    $tenant = new Tenant(['name' => 'Company A']);
    $owner = new User([
        'name' => 'Second User',
        'email' => $existing->email,
        'password' => 'password',
    ]);

    expect(fn () => app(ProvisionTenant::class)->handle($tenant, $owner))
        ->toThrow(QueryException::class);

    expect(tenancy()->initialized)->toBeFalse();
    expect($tenant->database()->manager()->databaseExists($tenant->database()->getName()))->toBeFalse();
    $this->assertDatabaseCount('tenants', 0);
    $this->assertDatabaseCount('users', 1);
});
