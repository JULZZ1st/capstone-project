<?php

namespace App\Http\Controllers;

use App\Http\Requests\SystemSecurity\SystemSecurityIndexRequest;
use App\Services\SystemSecurity\SystemSecurityDashboardQuery;
use Illuminate\View\View;

class SystemSecurityController extends Controller
{
    public function index(SystemSecurityIndexRequest $request, SystemSecurityDashboardQuery $query): View
    {
        return view('staff-account-management', $query->get($request->validated()));
    }
}
