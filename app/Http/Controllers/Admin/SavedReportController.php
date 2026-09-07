<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSavedReportRequest;
use App\Models\SavedReport;
use App\Support\ReportRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SavedReportController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $rows = SavedReport::query()
            ->visibleTo($user)
            ->with('user:id,name')
            ->latest()
            ->get()
            ->map(function (SavedReport $r) use ($user) {
                $meta = ReportRegistry::get($r->report_key);
                if (! $meta) {
                    return null;
                }

                return [
                    'model'    => $r,
                    'title'    => __($meta['title_key']),
                    'url'      => route($meta['route'], $r->parameters ?? []),
                    'is_owner' => $r->user_id === $user->id,
                ];
            })
            ->filter()
            ->values();

        return view('admin.reports.saved.index', ['rows' => $rows]);
    }

    public function store(StoreSavedReportRequest $request): JsonResponse
    {
        $user = $request->user();
        $key  = $request->input('report_key');
        $meta = ReportRegistry::get($key);

        abort_unless($meta && $user->hasPermission($meta['permission']), 403);

        $shared = $request->boolean('is_shared');
        abort_if($shared && ! $user->hasPermission('reports.save_shared'), 403);

        $saved = SavedReport::create([
            'user_id'     => $user->id,
            'store_id'    => current_store_id() ?: null,
            'report_key'  => $key,
            'name'        => $request->input('name'),
            'description' => $request->input('description'),
            'parameters'  => ReportRegistry::sanitizeParams((array) $request->input('parameters', [])),
            'is_shared'   => $shared,
        ]);

        return response()->json([
            'ok'       => true,
            'id'       => $saved->id,
            'redirect' => route('admin.reports.saved.index'),
        ]);
    }

    public function destroy(Request $request, SavedReport $savedReport): JsonResponse
    {
        $user = $request->user();

        // Only the owner (or a super admin) can remove a saved report — a
        // shared report stays for everyone until its creator deletes it.
        abort_unless($savedReport->user_id === $user->id || $user->is_super_admin, 403);

        $savedReport->delete();

        return response()->json(['ok' => true]);
    }
}
