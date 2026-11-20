<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaxPageController extends Controller
{
    public function __invoke(Request $request): View
    {
        $tenantId = app(CurrentTenant::class)->requireId();
        $membership = $request->user()->tenants()->whereKey($tenantId)->firstOrFail();
        $roleId = $membership->pivot->role_id;
        $canManage = $roleId && Role::query()
            ->whereKey($roleId)
            ->whereHas('permissions', fn ($query) => $query->where('permissions.code', 'taxes.manage'))
            ->exists();
        $canManageSettings = $roleId && Role::query()
            ->whereKey($roleId)
            ->whereHas('permissions', fn ($query) => $query->where('permissions.code', 'settings.manage'))
            ->exists();

        return view('app.taxes', [
            'canManageSettings' => (bool) $canManageSettings, 
            'user' => $request->user(),
            'workspace' => $request->user()->currentTenant,
            'canManage' => $canManage,
        ]);
    }
}
