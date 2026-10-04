<?php

namespace App\Support\Traits;

use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            $prefix = static::getAuditActionPrefix();
            $tenantId = $model->getAttribute('tenant_id') ?? null;

            Audit::record([
                'action' => "{$prefix}.created",
                'entity_type' => class_basename($model),
                'entity_id' => (string) $model->getKey(),
                'tenant_id' => $tenantId ? (int) $tenantId : null,
                'before' => null,
                'after' => $model->getAttributes(),
            ]);
        });

        static::updated(function (Model $model): void {
            $prefix = static::getAuditActionPrefix();
            $tenantId = $model->getAttribute('tenant_id') ?? null;

            $changes = $model->getChanges();
            $original = [];
            foreach (array_keys($changes) as $key) {
                $original[$key] = $model->getOriginal($key);
            }

            Audit::record([
                'action' => "{$prefix}.updated",
                'entity_type' => class_basename($model),
                'entity_id' => (string) $model->getKey(),
                'tenant_id' => $tenantId ? (int) $tenantId : null,
                'before' => $original,
                'after' => $changes,
            ]);
        });

        static::deleted(function (Model $model): void {
            $prefix = static::getAuditActionPrefix();
            $tenantId = $model->getAttribute('tenant_id') ?? null;

            Audit::record([
                'action' => "{$prefix}.deleted",
                'entity_type' => class_basename($model),
                'entity_id' => (string) $model->getKey(),
                'tenant_id' => $tenantId ? (int) $tenantId : null,
                'before' => $model->getOriginal(),
                'after' => null,
            ]);
        });
    }

    /**
     * Determine the audit action prefix for this model.
     */
    protected static function getAuditActionPrefix(): string
    {
        return Str::snake(class_basename(static::class));
    }
}
