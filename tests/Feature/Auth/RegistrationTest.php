<?php

use Laravel\Fortify\Features;
use App\Models\User;

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
});
