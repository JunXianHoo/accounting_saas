<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class DashboardCache
{
    /**
     * @template T
     *
     * @param callable(): T $load
     * @return T
     */
    public static function remember(string $tenantId, string $date, callable $load): mixed
    {
        $cache = Cache::store();
        $version = $cache->get(self::versionKey($tenantId), 'initial');

        return $cache->remember("dashboard:{$tenantId}:{$date}:{$version}", now()->addMinutes(5), fn () => $load());
    }

    public static function invalidate(string $tenantId): void
    {
        Cache::store()->put(self::versionKey($tenantId), (string) Str::uuid(), now()->addDay());
    }

    private static function versionKey(string $tenantId): string
    {
        return "dashboard:{$tenantId}:version";
    }
}
