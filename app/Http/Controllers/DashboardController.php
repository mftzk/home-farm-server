<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('dashboard', [
            'mode' => session('dashboard_mode', 'read'),
        ]);
    }
}
