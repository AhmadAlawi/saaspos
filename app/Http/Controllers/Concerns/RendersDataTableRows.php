<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shared plumbing for server-paginated list tables.
 *
 * Every admin list screen renders only its first page inline and fetches
 * each subsequent page / search / filter / sort from a `rows()` endpoint.
 * The `dataTableServer` Alpine mixin (resources/js/admin/data-table-server.js)
 * consumes a fixed JSON envelope:
 *
 *     { html, total, page, per_page, total_pages, ...extra }
 *
 * where `html` is the rendered rows fragment and `extra` carries per-page
 * meta the host needs (summary cards, counters). This trait standardises
 * page-size resolution and the envelope so a controller's `rows()` action
 * is query + partial + filters, not JSON bookkeeping.
 *
 * Usage:
 *
 *     public function rows(Request $request): JsonResponse
 *     {
 *         $this->authorize('viewAny', Customer::class);
 *
 *         $paginator = $this->listQuery($request)
 *             ->paginate($this->dtPerPage($request), ['*'], 'page', $this->dtPage($request));
 *
 *         return $this->dtRows($paginator, 'admin.customers._rows', 'customers', [
 *             'summary' => $this->summaryFor($request),
 *         ]);
 *     }
 */
trait RendersDataTableRows
{
    /** Default rows per page when the request doesn't say. Override per controller. */
    protected int $dtDefaultPerPage = 25;

    /** Hard ceiling so a crafted `per_page` can't ask the DB for everything. */
    protected int $dtMaxPerPage = 100;

    /** Requested page size, clamped to [1, dtMaxPerPage]. */
    protected function dtPerPage(Request $request, ?int $default = null): int
    {
        $perPage = (int) $request->query('per_page', $default ?? $this->dtDefaultPerPage);

        return max(1, min($perPage, $this->dtMaxPerPage));
    }

    /** Requested page, never below 1. */
    protected function dtPage(Request $request): int
    {
        return max(1, (int) $request->query('page', 1));
    }

    /**
     * Build the standard rows envelope from a paginator + a rows partial.
     *
     * Returning rendered Blade (not JSON records) keeps the row markup in one
     * place — the partial the inline first page also uses — instead of a second
     * copy as a JS template.
     *
     * @param  LengthAwarePaginator<int, \Illuminate\Database\Eloquent\Model>  $paginator
     * @param  string  $partial    view rendering the rows fragment
     * @param  string  $rowsVar    variable name the partial expects (e.g. 'customers')
     * @param  array<string, mixed>  $extra     merged into the envelope (e.g. ['summary' => …])
     * @param  array<string, mixed>  $viewData  extra vars the partial needs (e.g. a shared
     *                                          lookup map), merged into the view alongside `$rowsVar`
     */
    protected function dtRows(
        LengthAwarePaginator $paginator,
        string $partial,
        string $rowsVar,
        array $extra = [],
        array $viewData = [],
    ): JsonResponse {
        $html = view($partial, array_merge($viewData, [$rowsVar => $paginator->getCollection()]))->render();

        return response()->json(array_merge([
            'html'        => $html,
            'total'       => $paginator->total(),
            'page'        => $paginator->currentPage(),
            'per_page'    => $paginator->perPage(),
            'total_pages' => max(1, $paginator->lastPage()),
        ], $extra));
    }
}
