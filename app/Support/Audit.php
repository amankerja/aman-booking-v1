<?php

namespace App\Support;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\User;
use App\Domain\Tenant\Models\Tenant;

class Audit
{
    /**
     * Sensitive field keys that must be stripped from before and after payloads.
     *
     * @var array<int, string>
     */
    protected static array $sensitiveKeys = [
        'password',
        'password_confirmation',
        'token',
        'remember_token',
        'secret',
        'api_key',
        'access_token',
        'refresh_token',
        'private_key',
        'credit_card',
        'cvv',
    ];

    /**
     * Record a new audit log entry.
     *
     * @param  array{
     *     action: string,
     *     entity_type: string,
     *     entity_id: string|int,
     *     tenant_id?: int|null,
     *     actor_id?: int|null,
     *     actor_type?: string|null,
     *     actor_role?: string|null,
     *     before?: array<string, mixed>|null,
     *     after?: array<string, mixed>|null,
     *     source?: string|null,
     *     ip?: string|null,
     *     user_agent?: string|null
     * }  $data
     */
    public static function record(array $data): AuditLog
    {
        $tenantId = array_key_exists('tenant_id', $data)
            ? $data['tenant_id']
            : TenantContext::getTenantId();

        /** @var User|null $actor */
        $actor = auth()->user();

        $actorId = array_key_exists('actor_id', $data)
            ? $data['actor_id']
            : $actor?->id;

        $actorType = $data['actor_type'] ?? ($actor ? 'user' : (app()->runningInConsole() ? 'system' : 'api'));

        $actorRole = $data['actor_role'] ?? static::resolveActorRole($actor, $tenantId);

        $before = isset($data['before'])
            ? static::sanitize($data['before'])
            : null;

        $after = isset($data['after'])
            ? static::sanitize($data['after'])
            : null;

        $source = $data['source'] ?? (app()->runningInConsole() ? 'system' : 'web');
        $ip = array_key_exists('ip', $data) ? $data['ip'] : request()->ip();
        $userAgent = array_key_exists('user_agent', $data) ? $data['user_agent'] : request()->userAgent();

        return AuditLog::create([
            'tenant_id' => $tenantId,
            'actor_id' => $actorId,
            'actor_type' => $actorType,
            'actor_role' => $actorRole,
            'action' => $data['action'],
            'entity_type' => $data['entity_type'],
            'entity_id' => (string) $data['entity_id'],
            'before' => $before,
            'after' => $after,
            'source' => $source,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'created_at' => now(),
        ]);
    }

    /**
     * Sanitize array by stripping sensitive values.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function sanitize(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            $lowerKey = strtolower((string) $key);

            if (in_array($lowerKey, static::$sensitiveKeys, true)) {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = static::sanitize($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Resolve the actor's primary role in the current tenant or system.
     */
    protected static function resolveActorRole(?User $user, ?int $tenantId): string
    {
        if (! $user) {
            return app()->runningInConsole() ? 'SYSTEM' : 'GUEST';
        }

        if ($user->is_super_admin) {
            return 'SUPER_ADMIN';
        }

        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if ($tenant && $tenant->owner_user_id === $user->id) {
                return 'OWNER';
            }

            if (function_exists('setPermissionsTeamId')) {
                setPermissionsTeamId($tenantId);
            }

            $roles = $user->getRoleNames();
            if ($roles->isNotEmpty()) {
                return strtoupper(str_replace(' ', '_', $roles->first()));
            }
        }

        return 'MEMBER';
    }
}
