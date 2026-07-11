<?php

namespace App\Http\Controllers;

use App\Support\Dashboard\DashboardMetrics;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(DashboardMetrics $metrics): View
    {
        return view('dashboard', [
            'dashboard' => $metrics->build(),
        ]);
    }
}
