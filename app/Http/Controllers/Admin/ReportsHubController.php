<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportsHubController extends Controller
{
    public function __invoke(Request $request): View
    {
        // Any report viewer can open the hub — each card is itself gated, so
        // people only follow through to the reports they may actually see.
        $user = $request->user();
        $canViewAny = collect([
            'reports.view_financial', 'reports.view_sales', 'reports.view_inventory',
            'reports.view_customers', 'reports.view_suppliers', 'reports.view_employees', 'reports.view_tax',
        ])->contains(fn ($p) => $user?->hasPermission($p));

        abort_unless($canViewAny, 403);

        return view('admin.reports.index');
    }
}
