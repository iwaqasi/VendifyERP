<?php

namespace App\Traits;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    /**
     * Boot the BelongsToTenant trait for an Eloquent model.
     */
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function (Model $model) {
            if (empty($model->business_id)) {
                $businessId = static::resolveTenantBusinessId();
                if (!empty($businessId)) {
                    $model->business_id = $businessId;
                }
            }
        });
    }

    /**
     * Resolve the active tenant business ID from authenticated context, session, or request.
     */
    public static function resolveTenantBusinessId(): ?int
    {
        // 1. Authenticated user business_id
        if (auth()->check() && !empty(auth()->user()->business_id)) {
            return (int) auth()->user()->business_id;
        }

        // 2. Web session business_id
        if (session()->has('user.business_id')) {
            return (int) session()->get('user.business_id');
        }

        // 3. Request header (e.g. for microservices / API gateway)
        if (request()->hasHeader('X-Business-Id')) {
            return (int) request()->header('X-Business-Id');
        }

        return null;
    }

    /**
     * Scope a query to only include records of a specific business.
     */
    public function scopeForBusiness(Builder $query, int $businessId): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class)->where($this->getTable() . '.business_id', $businessId);
    }

    /**
     * Disable the tenant global scope on a query for administrative cross-tenant operations.
     */
    public static function withoutTenantScope(): Builder
    {
        return static::withoutGlobalScope(TenantScope::class);
    }

    /**
     * Relationship to Business
     */
    public function business()
    {
        return $this->belongsTo(\App\Business::class, 'business_id');
    }
}

