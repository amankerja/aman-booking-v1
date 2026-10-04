<?php

namespace App\Support;

use App\Domain\Tenant\Models\Tenant;

class TenantContext
{
    protected static ?Tenant $tenant = null;

    protected static bool $strict = false;

    public static function setTenant(?Tenant $tenant): void
    {
        static::$tenant = $tenant;

        if ($tenant) {
            setPermissionsTeamId($tenant->id);
        }
    }

    public static function setStrict(bool $strict = true): void
    {
        static::$strict = $strict;
    }

    public static function isStrict(): bool
    {
        return static::$strict;
    }

    public static function getTenant(): ?Tenant
    {
        return static::$tenant;
    }

    public static function getTenantId(): ?int
    {
        return static::$tenant?->id;
    }

    public static function hasTenant(): bool
    {
        return static::$tenant !== null;
    }

    public static function clear(): void
    {
        static::$tenant = null;
        static::$strict = false;

        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId(null);
        }
    }
}
