<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AuditLogger
{
    public function record(string $action, ?Model $subject = null, array $properties = [], ?Request $request = null): void
    {
        $request ??= app()->bound('request') ? request() : null;

        $tenantId = app(CurrentTenant::class)->id();

        AuditLog::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $request?->user()?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'properties' => $properties,
            'ip_address' => $request?->ip(),
        ]);

        if ($tenantId !== null) {
            Cache::forget("tenant:{$tenantId}:dashboard:v2");
        }
    }
}
