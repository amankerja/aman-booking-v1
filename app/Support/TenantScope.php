<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (TenantContext::hasTenant()) {
            $builder->where($model->qualifyColumn('tenant_id'), TenantContext::getTenantId());
        } elseif (TenantContext::isStrict()) {
            throw new \RuntimeException(
                'Query executed on tenant model ['.get_class($model).'] without an active TenantContext.'
            );
        }
    }
}
