<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsPageController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('app.settings', [
            'user' => $request->user(),
            'workspace' => $request->user()->currentTenant,
        ]);
    }
}
