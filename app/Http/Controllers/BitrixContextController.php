<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithBitrixContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BitrixContextController extends Controller
{
    use InteractsWithBitrixContext;

    public function install(Request $request): View
    {
        return view('bitrix.install', [
            'portal' => $this->currentPortal($request),
            'user' => $this->currentPortalUser($request),
        ]);
    }

    public function entry(): RedirectResponse
    {
        return redirect()->route('dashboard.index');
    }
}
