<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Maintenance\ClearSampleData;
use App\Actions\System\CheckSystemHealth;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin → System Health. A read-only diagnostics board that tells the
 * operator (or support) at a glance whether the install is in good shape:
 * application config, database, storage, background jobs, PHP/server
 * requirements, and optional integrations. See {@see CheckSystemHealth}.
 *
 * Also hosts the "danger zone" — clearing sample/demo data — since that's a
 * whole-install maintenance action, not a per-feature setting.
 */
class SystemHealthController extends Controller
{
    public function index(Request $request, CheckSystemHealth $check): View
    {
        abort_unless($request->user()?->hasPermission('settings.view'), 403);

        return view('admin.settings.system-health', ($check)());
    }

    /**
     * Wipe all business/sample data (keeps company, stores, users, settings,
     * chart of accounts). Super-admin only; blocked on the public demo.
     */
    public function clearSampleData(Request $request, ClearSampleData $clear): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->is_super_admin, 403);
        abort_if(pos_is_demo(), 403);

        $result  = ($clear)($request->user()?->id, backup: true);
        $rows    = array_sum($result['cleared']);
        $message = __('system_health.clear_sample.flash', ['rows' => number_format($rows)]);

        if ($request->wantsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('success', $message);
    }
}
