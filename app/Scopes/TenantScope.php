<?php

namespace App\Scopes;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Apply the tenant scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $businessId = BelongsToTenant::resolveTenantBusinessId();

        // Apply strict business_id constraint whenever tenant context is present
        if (!empty($businessId)) {
            $builder->where($model->getTable() . '.business_id', $businessId);
        }
    }
}

