<?php

namespace App\Traits;

use App\Support\CurrentTenant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenantId = app(CurrentTenant::class)->id();

            // Fail closed: unscoped requests must never see tenant-owned records.
            if ($tenantId === null) {
                $builder->whereRaw('1 = 0');
                return;
            }

            $builder->where($builder->getModel()->qualifyColumn('tenant_id'), $tenantId);
        });

        static::creating(function (Model $model): void {
            $tenantId = app(CurrentTenant::class)->requireId();
            $providedTenantId = $model->getAttribute('tenant_id');

            if ($providedTenantId !== null && (int) $providedTenantId !== $tenantId) {
                throw new AuthorizationException('Cannot create a record in another workspace.');
            }

            $model->setAttribute('tenant_id', $tenantId);
        });
    }

    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    /** Explicit escape hatch for reviewed platform-admin/background operations only. */
    public function scopeWithoutTenantScopeForSystem(Builder $query): Builder
    {
        if (! app()->runningInConsole()) {
            throw new LogicException('Cross-tenant reads are restricted to trusted system jobs.');
        }

        return $query->withoutGlobalScope('tenant');
    }
}
