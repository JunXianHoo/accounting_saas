<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ListTenantDatabases extends Command
{
    protected $signature = 'tenants:databases {company? : Part of the company name}';

    protected $description = 'Find the database name for a company';

    public function handle(): int
    {
        $search = trim((string) $this->argument('company'));
        $rows = [];

        foreach (Tenant::query()->orderBy('id')->cursor() as $tenant) {
            $name = (string) $tenant->getAttribute('name');

            if ($search === '' || Str::contains($name, $search, ignoreCase: true)) {
                $rows[] = [$name, $tenant->getTenantKey(), $tenant->database()->getName()];
            }
        }

        if ($rows === []) {
            $this->info('No matching companies found.');

            return self::SUCCESS;
        }

        $this->table(['Company', 'Tenant ID', 'Database'], $rows);

        return self::SUCCESS;
    }
}
