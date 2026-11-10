<?php

namespace App\Actions\Tenants;

use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Throwable;

class ProvisionTenant
{
    /** @param (Closure(): void)|null $seed */
    public function handle(Tenant $tenant, ?User $owner = null, ?Closure $seed = null): Tenant
    {
        if (tenancy()->initialized || $tenant->exists) {
            throw new LogicException('A new tenant must be provisioned from the central context.');
        }

        $tenant->id ??= (string) Str::uuid();
        $database = $tenant->database();
        $database->makeCredentials();
        $manager = $database->manager();

        if ($manager->databaseExists($database->getName()) || ! $manager->createDatabase($tenant)) {
            throw new RuntimeException('Unable to create the company database.');
        }

        try {
            try {
                tenancy()->initialize($tenant);

                $status = Artisan::call('migrate', [
                    '--database' => 'tenant',
                    '--path' => database_path('migrations/tenant'),
                    '--realpath' => true,
                    '--force' => true,
                ]);

                if ($status !== 0) {
                    throw new RuntimeException('Unable to migrate the company database.');
                }

                if ($seed !== null) {
                    DB::transaction($seed);
                }
            } finally {
                tenancy()->end();
            }

            return $tenant->getConnection()->transaction(function () use ($tenant, $owner): Tenant {
                $tenant->save();

                if ($owner !== null) {
                    $owner->tenant()->associate($tenant);
                    $owner->save();
                }

                return $tenant;
            });
        } catch (Throwable $exception) {
            try {
                $manager->deleteDatabase($tenant);
            } catch (Throwable $cleanupException) {
                report($cleanupException);
            }

            throw $exception;
        }
    }
}
