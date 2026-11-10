<?php

namespace Tests;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function assertTenantDatabaseHas(string $table, array $data, ?Tenant $tenant = null): void
    {
        $this->inTenantDatabase($tenant, fn () => $this->assertDatabaseHas($table, $data, 'tenant'));
    }

    protected function assertTenantDatabaseMissing(string $table, array $data, ?Tenant $tenant = null): void
    {
        $this->inTenantDatabase($tenant, fn () => $this->assertDatabaseMissing($table, $data, 'tenant'));
    }

    protected function assertTenantDatabaseCount(string $table, int $count, ?Tenant $tenant = null): void
    {
        $this->inTenantDatabase($tenant, fn () => $this->assertDatabaseCount($table, $count, 'tenant'));
    }

    private function inTenantDatabase(?Tenant $tenant, callable $assert): void
    {
        $previousTenant = tenant();
        $tenant ??= $previousTenant ?? auth()->user()?->tenant;
        $this->assertInstanceOf(Tenant::class, $tenant);

        try {
            tenancy()->initialize($tenant);
            $assert();
        } finally {
            if ($previousTenant !== null) {
                tenancy()->initialize($previousTenant);
            } else {
                tenancy()->end();
            }
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
