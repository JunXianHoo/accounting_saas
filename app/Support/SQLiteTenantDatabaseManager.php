<?php

namespace App\Support;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager;

class SQLiteTenantDatabaseManager extends SQLiteDatabaseManager
{
    public function createDatabase(TenantWithDatabase $tenant): bool
    {
        $file = fopen(database_path($tenant->database()->getName()), 'x');

        return $file !== false && fclose($file);
    }
}
