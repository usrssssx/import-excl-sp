<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class BitrixContextController extends Controller
{
    public function install(): View
    {
        return view('bitrix.install');
    }

    public function entry(Request $request, DashboardController $dashboardController): View
    {
        return $dashboardController->index($request);
    }
}
