<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\DashboardDataService;
use App\Support\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardDataService $dashboard): View
    {
        $tenantId = app(CurrentTenant::class)->requireId();
        $membership = $request->user()->tenants()->whereKey($tenantId)->first();
        $roleId = $membership?->pivot?->role_id;
        $canManageSettings = $roleId && Role::query()
            ->whereKey($roleId)
            ->whereHas('permissions', fn ($query) => $query->where('permissions.code', 'settings.manage'))
            ->exists();

        return view('app.dashboard', [
            'canManageSettings' => (bool) $canManageSettings,
            'tenantRole' => $membership?->pivot?->role,
            'user' => $request->user(),
            'workspace' => $request->user()->currentTenant,
            'data' => $dashboard->forUser($request->user()),
        ]);
    }
}
