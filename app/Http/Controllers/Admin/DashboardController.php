<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Dashboard\ResolveSetupCard;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shift;
use App\Services\Reports\DailyMetricsReader;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Resolve the header date filter into an inclusive [$from, $to] range.
        // Presets (?range=today|yesterday|7d|15d|30d|60d|90d|custom), a custom
        // ?from/?to span, and the legacy ?date=YYYY-MM-DD single day are all
        // supported. Defaults to today.
        [$from, $to, $rangeKey, $isSingleDay] = $this->resolveRange($request);

        $storeId  = session('active_store_id');
        $fromDate = $from->toDateString();
        $toDate   = $to->toDateString();

        // The anchor used by trailing-window charts (14-day trend, hourly,
        // heatmap) and by the blade header labels is the range's end date.
        $baseCdt = $to->copy();
        $isToday = $isSingleDay && $to->isToday();
        $today   = $toDate;
        $days    = (int) $from->diffInDays($to) + 1;

        // The real calendar "today" — used by the always-current widgets
        // (Operations strip "Today's revenue" + the Top-products card) that
        // deliberately ignore the header date filter. Distinct from $today,
        // which is the *selected range's* end date used as the trend anchor.
        $actualToday = now()->toDateString();

        // ── KPIs over the selected range ──────────────────────────────
        $todayStats = Sale::completed()
            ->forStore($storeId)
            ->whereBetween('sale_date', [$fromDate, $toDate])
            ->selectRaw('COUNT(*) as txn_count, COALESCE(SUM(grand_total), 0) as total_sales')
            ->first();

        $todaySales = (float) ($todayStats->total_sales ?? 0);
        $todayTxns  = (int)   ($todayStats->txn_count  ?? 0);
        $avgBasket  = $todayTxns > 0 ? round($todaySales / $todayTxns, 4) : 0.0;

        // "Today's revenue" on the Operations strip always reflects the real
        // calendar day, regardless of the header date filter (the strip is
        // live "now" data). Reuse the range figure when the range already IS
        // today to save a query; otherwise sum today's completed sales.
        $todayRevenue = $isToday
            ? $todaySales
            : (float) Sale::completed()
                ->forStore($storeId)
                ->whereDate('sale_date', $actualToday)
                ->sum('grand_total');

        $todayItems = (float) SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.store_id', $storeId)
            ->whereBetween('sales.sale_date', [$fromDate, $toDate])
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->sum('sale_items.quantity');

        $newCustomers = Customer::query()
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->count();

        $todayRefunds = 0.0;
        if (DB::getSchemaBuilder()->hasTable('sale_returns')) {
            $todayRefunds = (float) DB::table('sale_returns')
                ->join('sales', 'sales.id', '=', 'sale_returns.sale_id')
                ->where('sales.store_id', $storeId)
                ->whereBetween('sale_returns.return_date', [$fromDate, $toDate])
                ->sum('sale_returns.grand_total');
        }

        // ── Hero chart series ─────────────────────────────────────────
        // Single-day ranges show an hourly profile; multi-day ranges show
        // a daily revenue series spanning [$from, $to].
        [$heroSeries, $heroLabels, $heroIsHourly] = $this->heroSeries(
            $storeId, $from, $to, $isSingleDay, $days
        );

        // ── 14-day trend ──────────────────────────────────────────────
        // Sourced via DailyMetricsReader: settled days come from the
        // daily_metrics pre-aggregation (docs §13); today and any un-settled
        // / stale day fall back to a live query automatically, so numbers are
        // always correct — just cheaper once the nightly refresh has run.
        $trendSeries = app(DailyMetricsReader::class)->dailySeries(
            (int) $storeId,
            CarbonImmutable::parse($baseCdt->copy()->subDays(13)->toDateString()),
            CarbonImmutable::parse($baseCdt->toDateString()),
        );

        $revByDay = $txnByDay = $dayLabels = [];
        for ($i = 13; $i >= 0; $i--) {
            $date        = $baseCdt->copy()->subDays($i)->toDateString();
            $row         = $trendSeries[$date] ?? null;
            $dayLabels[] = $baseCdt->copy()->subDays($i)->format('M d');
            $revByDay[]  = (float) ($row['sales_gross'] ?? 0);
            $txnByDay[]  = (int)   ($row['sales_count'] ?? 0);
        }

        $period14Sales = array_sum($revByDay);
        $period14Txns  = array_sum($txnByDay);
        $period14Avg   = $period14Txns > 0 ? round($period14Sales / $period14Txns, 4) : 0.0;

        // ── Gross margin + "current 14d vs previous 14d" trends ────────
        // Margin = (net revenue − COGS) / net revenue. Revenue basis is
        // net-of-tax (sale_items.line_subtotal — tax is pass-through, not
        // profit); COGS uses the per-line cost captured at sale time
        // (unit_cost_snapshot). Computed over the same trailing 14 days as
        // the KPIs above, compared against the preceding 14 days.
        $curFrom  = $baseCdt->copy()->subDays(13)->toDateString();
        $prevTo   = $baseCdt->copy()->subDays(14)->toDateString();
        $prevFrom = $baseCdt->copy()->subDays(27)->toDateString();
        $cur  = $this->perfWindow($storeId, $curFrom, $today);
        $prev = $this->perfWindow($storeId, $prevFrom, $prevTo);

        $pctChange = function (?float $now, ?float $was): ?float {
            if ($now === null || $was === null || $was == 0.0) {
                return null;
            }
            return round(($now - $was) / abs($was) * 100, 1);
        };

        $period14Margin      = $cur['margin'];
        $period14MarginLabel = $period14Margin === null ? '—' : number_format($period14Margin, 1).'%';
        $netTrendChip        = $this->trendChip($pctChange($period14Sales, $prev['net']));
        $basketTrendChip     = $this->trendChip($pctChange($period14Avg,   $prev['avg']));
        $marginTrendChip     = $this->trendChip($pctChange($cur['margin'], $prev['margin']));

        // ── Ops strip ─────────────────────────────────────────────────
        // MUST mirror LowStockReportController's predicate exactly so the
        // dashboard card and the Low-stock listing always agree: only rows
        // with an effective reorder threshold SET (no COALESCE(...,0)
        // fallback) and quantity at/below it. Same active store as the card.
        $lowStockCount = DB::table('product_stock_levels as psl')
            ->join('products', 'products.id', '=', 'psl.product_id')
            ->whereNull('products.deleted_at')
            ->where('psl.store_id', $storeId)
            ->whereNotNull(DB::raw('COALESCE(psl.reorder_level_override, products.reorder_level)'))
            ->whereColumn('psl.quantity', '<=', DB::raw('COALESCE(psl.reorder_level_override, products.reorder_level)'))
            ->count();

        // Oversold = on-hand gone negative (sold below stock). Mirrors
        // OversoldReportController; same active store as the card links to.
        $oversoldCount = DB::table('product_stock_levels as psl')
            ->join('products', 'products.id', '=', 'psl.product_id')
            ->whereNull('products.deleted_at')
            ->where('psl.store_id', $storeId)
            ->where('psl.quantity', '<', 0)
            ->count();

        $posInTransit      = 0;
        $posInTransitValue = 0.0;
        try {
            $poRow = DB::table('purchases')
                ->where('store_id', $storeId)
                ->where('status', Purchase::STATUS_SUBMITTED)
                ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(grand_total), 0) as total')
                ->first();
            $posInTransit      = (int)   ($poRow?->cnt   ?? 0);
            $posInTransitValue = (float) ($poRow?->total ?? 0);
        } catch (\Throwable) {
            // table may not exist in early installs
        }

        // Count open shifts (active cashiers right now), not sales-derived
        $cashiersOnShift = Shift::forStore($storeId)
            ->open()
            ->whereDate('opened_at', '<=', $today)
            ->count();

        // ── Top products (today) ───────────────────────────────────────
        // The catalog card carries its OWN range selector (defaulting to
        // "today"), so this initial render is scoped to the real calendar
        // day — NOT the header date filter — to match that default. The
        // selector's AJAX (catalogRange) re-queries when the user changes it.
        $topProductsRaw = SaleItem::query()
            ->join('sales',      'sales.id',      '=', 'sale_items.sale_id')
            ->join('products',   'products.id',   '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('sales.store_id', $storeId)
            ->whereDate('sales.sale_date', $actualToday)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->selectRaw('
                sale_items.product_id,
                products.name,
                products.image_path,
                categories.name as category_name,
                CAST(SUM(sale_items.quantity) AS DECIMAL(15,4)) as units_sold,
                CAST(SUM(sale_items.line_total) AS DECIMAL(15,4)) as revenue
            ')
            ->groupBy('sale_items.product_id', 'products.name', 'products.image_path', 'categories.name')
            ->orderByDesc('revenue')
            ->limit(8)
            ->get();

        // Anchor the sparkline trends to today (default), matching the
        // catalogRange 'today' path — not the selected range's end date.
        $productTrends = $this->productTrends(
            $topProductsRaw->pluck('product_id')->all(),
            $storeId
        );

        $topProducts = $topProductsRaw->map(fn ($p) => [
            'id'       => $p->product_id,
            'name'     => $p->name,
            'image'    => $p->image_path ? asset('storage/'.$p->image_path) : null,
            'category' => $p->category_name,
            'units'    => (float) $p->units_sold,
            'revenue'  => (float) $p->revenue,
            'trend'    => $productTrends[$p->product_id] ?? array_fill(0, 7, 0),
        ]);

        // ── Low-stock items ────────────────────────────────────────────
        $lowStockItems = DB::table('product_stock_levels as psl')
            ->where('psl.store_id', $storeId)
            ->whereNull('psl.variant_id')
            ->join('products', 'products.id', '=', 'psl.product_id')
            ->selectRaw('
                products.id,
                products.name,
                products.image_path,
                psl.quantity as qty,
                COALESCE(psl.reorder_level_override, products.reorder_level, 5) as reorder_at,
                GREATEST(COALESCE(products.reorder_level, 5) * 2, 10) as par_level
            ')
            ->orderByRaw('(psl.quantity / GREATEST(COALESCE(products.reorder_level, 1), 1)) ASC')
            ->limit(6)
            ->get()
            ->map(fn ($r) => [
                'id'       => $r->id,
                'name'     => $r->name,
                'image'    => $r->image_path ? asset('storage/'.$r->image_path) : null,
                'qty'      => (float) $r->qty,
                'reorderAt'=> (float) $r->reorder_at,
                'parLevel' => (float) $r->par_level,
            ]);

        // ── Category mix (donut) ───────────────────────────────────────
        $salesByCategory = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('sales.store_id', $storeId)
            ->whereBetween('sales.sale_date', [$fromDate, $toDate])
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->selectRaw('COALESCE(categories.name, ?) as cat_name, CAST(SUM(sale_items.line_total) AS DECIMAL(15,4)) as rev', [__('admin.dashboard.uncategorized')])
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('rev')
            ->limit(8)
            ->get()
            ->map(fn ($r) => ['name' => $r->cat_name, 'rev' => (float) $r->rev]);

        // ── Heatmap: (day-of-week × hour) for last 7 days ─────────────
        $since7 = $baseCdt->copy()->subDays(6)->startOfDay();
        $hmRows = Sale::completed()
            ->forStore($storeId)
            ->where('sale_datetime', '>=', $since7)
            ->selectRaw('DAYOFWEEK(sale_datetime) as dow, HOUR(sale_datetime) as hr, COALESCE(SUM(grand_total), 0) as rev, COUNT(*) as txns')
            ->groupBy('dow', 'hr')
            ->get();

        // Build flat [dow(0=Mon..6=Sun)][hr] matrices (explicit loops avoid PHP's
        // copy-on-write aliasing that array_fill with an array value can cause).
        // Revenue drives the cell colour; the txn count feeds the hover tooltip.
        $heatmapData  = [];
        $heatmapTxns  = [];
        $heatmapItems = [];
        for ($d = 0; $d < 7; $d++) {
            $heatmapData[$d]  = array_fill(0, 24, 0.0);
            $heatmapTxns[$d]  = array_fill(0, 24, 0);
            $heatmapItems[$d] = array_fill(0, 24, 0.0);
        }
        foreach ($hmRows as $row) {
            // MySQL DAYOFWEEK: 1=Sun,2=Mon..7=Sat → remap: Mon=0..Sun=6
            $dow = ($row->dow + 5) % 7;
            $heatmapData[$dow][$row->hr] = (float) $row->rev;
            $heatmapTxns[$dow][$row->hr] = (int) $row->txns;
        }

        // Units sold per (day-of-week × hour) — feeds the "Items sold" line in
        // the heatmap hover tooltip. Separate query because it sums sale_items.
        if (\Illuminate\Support\Facades\Schema::hasTable('sale_items')) {
            $itemRows = DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->where('sales.status', Sale::STATUS_COMPLETED)
                ->where('sales.store_id', $storeId)
                ->where('sales.sale_datetime', '>=', $since7)
                ->selectRaw('DAYOFWEEK(sales.sale_datetime) as dow, HOUR(sales.sale_datetime) as hr, COALESCE(SUM(sale_items.quantity), 0) as qty')
                ->groupBy('dow', 'hr')
                ->get();
            foreach ($itemRows as $row) {
                $dow = ($row->dow + 5) % 7;
                $heatmapItems[$dow][$row->hr] = (float) $row->qty;
            }
        }

        // ── Activity feed ──────────────────────────────────────────────
        $activity = Sale::completed()
            ->forStore($storeId)
            ->with('cashier:id,name')
            ->orderByDesc('sale_datetime')
            ->limit(8)
            ->get(['id', 'cashier_id', 'grand_total', 'sale_datetime'])
            ->map(fn ($s) => [
                'icon'   => 'receipt',
                'who'    => $s->cashier?->name ?? __('admin.dashboard.guest_cashier'),
                'what'   => __('admin.dashboard.activity.completed_sale'),
                'target' => format_money($s->grand_total),
                'when'   => $s->sale_datetime?->diffForHumans() ?? '',
            ]);

        // ── Shift staff ────────────────────────────────────────────────
        // Drive from shifts table so open shifts with zero sales still appear.
        // Include: shifts opened today OR any still-open shift for this store.
        // Shifts active on $today:
        //   (a) opened on $today (regardless of status), OR
        //   (b) opened before $today and still open (multi-day / overnight shift)
        $shiftStaff = DB::table('shifts')
            ->join('users', 'users.id', '=', 'shifts.user_id')
            ->leftJoin('sales', function ($join) {
                $join->on('sales.shift_id', '=', 'shifts.id')
                     ->where('sales.status', Sale::STATUS_COMPLETED);
            })
            ->where('shifts.store_id', $storeId)
            ->where(function ($q) use ($today) {
                $q->whereDate('shifts.opened_at', $today)
                  ->orWhere(function ($q2) use ($today) {
                      $q2->where('shifts.status', Shift::STATUS_OPEN)
                         ->whereDate('shifts.opened_at', '<', $today);
                  });
            })
            ->selectRaw('
                shifts.id as shift_id,
                shifts.status,
                users.name,
                COUNT(sales.id) as txns,
                COALESCE(SUM(sales.grand_total), 0) as sales_total
            ')
            ->groupBy('shifts.id', 'shifts.status', 'users.name')
            ->orderByRaw("FIELD(shifts.status, 'open', 'closed_with_variance', 'closed')")
            ->orderByDesc('sales_total')
            ->get()
            ->map(fn ($u) => [
                'id'     => $u->shift_id,
                'name'   => $u->name,
                'sales'  => (float) $u->sales_total,
                'txns'   => (int)   $u->txns,
                'status' => $u->status === Shift::STATUS_OPEN ? 'on' : 'off',
            ]);

        // ── Build Alpine config (all chart data as one JSON blob) ──────
        $alpineConfig = [
            'heroSeries'      => $heroSeries,
            'heroLabels'      => $heroLabels,
            'heroIsHourly'    => $heroIsHourly,
            'revByDay'        => $revByDay,
            'txnByDay'        => $txnByDay,
            'dayLabels'       => $dayLabels,
            'topProducts'     => $topProducts->values()->toArray(),
            'lowStockItems'   => $lowStockItems->values()->toArray(),
            'salesByCategory' => $salesByCategory->values()->toArray(),
            'heatmapData'     => $heatmapData,
            'heatmapTxns'     => $heatmapTxns,
            'heatmapItems'    => $heatmapItems,
            'activity'        => $activity->values()->toArray(),
            'shiftStaff'      => $shiftStaff->values()->toArray(),
            'chartRangeUrl'   => route('admin.dashboard.chart-range'),
            'catalogRangeUrl' => route('admin.dashboard.catalog-range'),
        ];

        // ── Setup checklist (new-install onboarding) ──────────────────
        // Null once it's dismissed or the owner has seen the one-time
        // "You're all set!" celebration. See ResolveSetupCard.
        $setup = app(ResolveSetupCard::class)();

        // Header label for the chosen range + custom-picker prefills.
        $rangeLabel  = $this->rangeLabel($rangeKey, $from, $to, $isSingleDay);
        $periodLabel = $rangeLabel;
        $customFrom  = $rangeKey === 'custom' ? $fromDate : '';
        $customTo    = $rangeKey === 'custom' ? $toDate : '';

        return view('admin.dashboard', compact(
            'todaySales', 'todayTxns', 'avgBasket', 'todayItems',
            'todayRevenue', 'newCustomers', 'todayRefunds',
            'period14Sales', 'period14Txns', 'period14Avg',
            'period14MarginLabel', 'netTrendChip', 'basketTrendChip', 'marginTrendChip',
            'lowStockCount', 'oversoldCount', 'posInTransit', 'posInTransitValue', 'cashiersOnShift',
            'alpineConfig', 'baseCdt', 'isToday', 'setup',
            'rangeKey', 'rangeLabel', 'periodLabel', 'isSingleDay', 'customFrom', 'customTo'
        ));
    }

    /** Dismiss the "Get your store ready" checklist (company-wide). */
    public function dismissSetup(): JsonResponse
    {
        Company::current()?->update(['dashboard_setup_dismissed' => true]);

        return response()->json(['ok' => true]);
    }

    /**
     * Resolve the header date filter into an inclusive [$from, $to] range.
     *
     * Inputs (first match wins):
     *   ?range=today|yesterday|7d|15d|30d|60d|90d|custom
     *   ?from=YYYY-MM-DD&to=YYYY-MM-DD  (implies a custom range)
     *   ?date=YYYY-MM-DD                (legacy single day)
     * Defaults to today. Custom spans are clamped (from ≤ to) and capped
     * at one year so a hand-edited URL can't ask for an unbounded scan.
     *
     * @return array{0:Carbon,1:Carbon,2:string,3:bool} [$from, $to, $rangeKey, $isSingleDay]
     */
    private function resolveRange(Request $request): array
    {
        $presets = ['7d' => 7, '15d' => 15, '30d' => 30, '60d' => 60, '90d' => 90];
        $range   = (string) $request->query('range', '');

        // Custom range — either range=custom with from/to, or bare from/to.
        $hasCustom = ($range === 'custom') || ($request->filled('from') && $request->filled('to'));
        if ($hasCustom && $request->filled('from') && $request->filled('to')) {
            try {
                $from = Carbon::parse($request->query('from'))->startOfDay();
                $to   = Carbon::parse($request->query('to'))->endOfDay();
                if ($from->gt($to)) {
                    [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
                }
                // Cap the span at 366 days.
                if ($from->diffInDays($to) > 366) {
                    $from = $to->copy()->subDays(366)->startOfDay();
                }
                return [$from, $to, 'custom', $from->isSameDay($to)];
            } catch (\Throwable) {
                // fall through to today on an unparseable custom range
            }
        }

        if (isset($presets[$range])) {
            $to   = now()->endOfDay();
            $from = now()->subDays($presets[$range] - 1)->startOfDay();
            return [$from, $to, $range, false];
        }

        if ($range === 'yesterday') {
            $d = now()->subDay();
            return [$d->copy()->startOfDay(), $d->copy()->endOfDay(), 'yesterday', true];
        }

        // Legacy ?date=YYYY-MM-DD single-day view.
        if ($request->filled('date')) {
            try {
                $d = Carbon::parse($request->query('date'));
                return [
                    $d->copy()->startOfDay(),
                    $d->copy()->endOfDay(),
                    $d->isToday() ? 'today' : 'custom',
                    true,
                ];
            } catch (\Throwable) {
                // fall through to today
            }
        }

        return [now()->startOfDay(), now()->endOfDay(), 'today', true];
    }

    /**
     * Build the hero area-chart series for the selected range. Single-day
     * ranges return a 24-point hourly profile; multi-day ranges return one
     * point per day across [$from, $to].
     *
     * @return array{0:array<int,float>,1:array<int,string>,2:bool} [$series, $labels, $isHourly]
     */
    private function heroSeries(int|string|null $storeId, Carbon $from, Carbon $to, bool $isSingleDay, int $days): array
    {
        if ($isSingleDay) {
            $rows = Sale::completed()
                ->forStore($storeId)
                ->whereDate('sale_date', $to->toDateString())
                ->selectRaw('HOUR(sale_datetime) as hr, COALESCE(SUM(grand_total), 0) as rev')
                ->groupBy('hr')->orderBy('hr')->get()->keyBy('hr');

            $series = $labels = [];
            for ($h = 0; $h < 24; $h++) {
                $series[] = (float) ($rows[$h]->rev ?? 0);
                $labels[] = sprintf('%02d:00', $h);
            }

            return [$series, $labels, true];
        }

        $rows = Sale::completed()
            ->forStore($storeId)
            ->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('DATE(sale_date) as d, COALESCE(SUM(grand_total), 0) as rev')
            ->groupBy('d')->orderBy('d')->get()->keyBy('d');

        $series = $labels = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date     = $to->copy()->subDays($i)->toDateString();
            $series[] = (float) ($rows[$date]->rev ?? 0);
            $labels[] = $to->copy()->subDays($i)->format('M d');
        }

        return [$series, $labels, false];
    }

    /** Human label for the header date-range button. */
    private function rangeLabel(string $rangeKey, Carbon $from, Carbon $to, bool $isSingleDay): string
    {
        return match ($rangeKey) {
            'today'     => __('admin.dashboard.date_range_today'),
            'yesterday' => __('admin.dashboard.date_range_yesterday'),
            '7d', '15d', '30d', '60d', '90d' => __('admin.dashboard.date_range_'.$rangeKey),
            default     => $isSingleDay
                ? $to->format('M j, Y')
                : $from->format('M j').' – '.$to->format('M j, Y'),
        };
    }

    /**
     * Sales + profitability aggregates over an inclusive [$from, $to] date
     * window, for the performance KPIs and their period-over-period trends.
     *
     * @return array{net:float,txns:int,avg:float,revenue_net:float,cogs:float,gross_profit:float,margin:?float}
     */
    private function perfWindow(int|string|null $storeId, string $from, string $to): array
    {
        $sales = Sale::completed()
            ->forStore($storeId)
            ->whereBetween('sale_date', [$from, $to])
            ->selectRaw('COUNT(*) as txns, COALESCE(SUM(grand_total), 0) as net')
            ->first();

        $items = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.store_id', $storeId)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereBetween('sales.sale_date', [$from, $to])
            ->selectRaw('
                COALESCE(SUM(sale_items.line_subtotal), 0) as revenue_net,
                COALESCE(SUM(sale_items.quantity * sale_items.unit_cost_snapshot), 0) as cogs
            ')
            ->first();

        $net    = (float) ($sales->net  ?? 0);
        $txns   = (int)   ($sales->txns ?? 0);
        $revNet = (float) ($items->revenue_net ?? 0);
        $cogs   = (float) ($items->cogs ?? 0);

        return [
            'net'          => $net,
            'txns'         => $txns,
            'avg'          => $txns > 0 ? $net / $txns : 0.0,
            'revenue_net'  => $revNet,
            'cogs'         => $cogs,
            'gross_profit' => $revNet - $cogs,
            'margin'       => $revNet > 0 ? ($revNet - $cogs) / $revNet * 100 : null,
        ];
    }

    /**
     * Format a period-over-period percentage change into the colour + label
     * the `.trend-chip` expects. Null (no comparable prior data) reads "—".
     *
     * @return array{class:string,label:string}
     */
    private function trendChip(?float $pct): array
    {
        if ($pct === null) {
            return ['class' => 'trend-flat', 'label' => '—'];
        }

        $class = $pct > 0 ? 'trend-up' : ($pct < 0 ? 'trend-down' : 'trend-flat');
        $sign  = $pct > 0 ? '+' : ($pct < 0 ? '−' : '');

        return ['class' => $class, 'label' => $sign.number_format(abs($pct), 1).'%'];
    }

    // ── AJAX: chart data for the performance range selector ───────────
    public function chartRange(Request $request): JsonResponse
    {
        $range   = in_array($request->input('range'), ['today', '7d', '14d', '30d'])
            ? $request->input('range')
            : '14d';
        $storeId = session('active_store_id');
        $today   = now()->toDateString();

        if ($range === 'today') {
            // Hourly buckets for today
            $hourlyRevRows = Sale::completed()
                ->forStore($storeId)
                ->whereDate('sale_date', $today)
                ->selectRaw('HOUR(sale_datetime) as hr, COALESCE(SUM(grand_total), 0) as rev')
                ->groupBy('hr')->orderBy('hr')->get()->keyBy('hr');

            $hourlyTxnRows = Sale::completed()
                ->forStore($storeId)
                ->whereDate('sale_date', $today)
                ->selectRaw('HOUR(sale_datetime) as hr, COUNT(*) as cnt')
                ->groupBy('hr')->orderBy('hr')->get()->keyBy('hr');

            $revByDay = $txnByDay = $dayLabels = [];
            for ($h = 0; $h < 24; $h++) {
                $revByDay[]  = (float) ($hourlyRevRows[$h]?->rev ?? 0);
                $txnByDay[]  = (int)   ($hourlyTxnRows[$h]?->cnt ?? 0);
                $dayLabels[] = sprintf('%02d:00', $h);
            }

            $since = $today;
            $dateFilter = fn ($q) => $q->whereDate('sales.sale_date', $today);
        } else {
            $days  = match($range) { '7d' => 7, '30d' => 30, default => 14 };
            $since = now()->subDays($days - 1)->toDateString();

            $salesByDay = Sale::completed()
                ->forStore($storeId)
                ->where('sale_date', '>=', $since)
                ->selectRaw('DATE(sale_date) as d, COUNT(*) as txns, COALESCE(SUM(grand_total), 0) as rev')
                ->groupBy('d')->orderBy('d')->get()->keyBy('d');

            $revByDay = $txnByDay = $dayLabels = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $date        = now()->subDays($i)->toDateString();
                $row         = $salesByDay[$date] ?? null;
                $dayLabels[] = now()->subDays($i)->format('M d');
                $revByDay[]  = (float) ($row?->rev  ?? 0);
                $txnByDay[]  = (int)   ($row?->txns ?? 0);
            }

            $dateFilter = fn ($q) => $q->where('sales.sale_date', '>=', $since);
        }

        // Category mix for the selected range
        $categoryQuery = SaleItem::query()
            ->join('sales',      'sales.id',      '=', 'sale_items.sale_id')
            ->join('products',   'products.id',   '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('sales.store_id', $storeId)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->selectRaw('COALESCE(categories.name, ?) as cat_name, CAST(SUM(sale_items.line_total) AS DECIMAL(15,4)) as rev',
                [__('admin.dashboard.uncategorized')])
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('rev')
            ->limit(8);

        $dateFilter($categoryQuery);

        $salesByCategory = $categoryQuery->get()
            ->map(fn ($r) => ['name' => $r->cat_name, 'rev' => (float) $r->rev])
            ->values()
            ->toArray();

        return response()->json(compact('revByDay', 'txnByDay', 'dayLabels', 'salesByCategory'));
    }

    // ── AJAX: top-products data for the catalog range selector ────────
    public function catalogRange(Request $request): JsonResponse
    {
        $range   = in_array($request->input('range'), ['today', '7d', '14d', '30d'])
            ? $request->input('range')
            : 'today';
        $storeId = session('active_store_id');
        $today   = now()->toDateString();

        if ($range === 'today') {
            $dateFilter = fn ($q) => $q->whereDate('sales.sale_date', $today);
        } else {
            $days  = match($range) { '7d' => 7, '14d' => 14, '30d' => 30, default => 7 };
            $since = now()->subDays($days - 1)->toDateString();
            $dateFilter = fn ($q) => $q->where('sales.sale_date', '>=', $since);
        }

        $topProductsQuery = SaleItem::query()
            ->join('sales',      'sales.id',      '=', 'sale_items.sale_id')
            ->join('products',   'products.id',   '=', 'sale_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('sales.store_id', $storeId)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->selectRaw('
                sale_items.product_id,
                products.name,
                products.image_path,
                categories.name as category_name,
                CAST(SUM(sale_items.quantity) AS DECIMAL(15,4)) as units_sold,
                CAST(SUM(sale_items.line_total) AS DECIMAL(15,4)) as revenue
            ')
            ->groupBy('sale_items.product_id', 'products.name', 'products.image_path', 'categories.name')
            ->orderByDesc('revenue')
            ->limit(8);

        $dateFilter($topProductsQuery);

        $rawProducts   = $topProductsQuery->get();
        $productTrends = $this->productTrends($rawProducts->pluck('product_id')->all(), $storeId);

        $topProducts = $rawProducts
            ->map(fn ($p) => [
                'id'       => $p->product_id,
                'name'     => $p->name,
                'image'    => $p->image_path ? asset('storage/'.$p->image_path) : null,
                'category' => $p->category_name,
                'units'    => (float) $p->units_sold,
                'revenue'  => (float) $p->revenue,
                'trend'    => $productTrends[$p->product_id] ?? array_fill(0, 7, 0),
            ])
            ->values()
            ->toArray();

        return response()->json(compact('topProducts'));
    }

    // ── Private: 7-day daily unit quantities for a set of products ────
    private function productTrends(array $productIds, int|string $storeId, ?Carbon $anchor = null): array
    {
        if (empty($productIds)) {
            return [];
        }

        $anchor ??= now()->startOfDay();

        $rows = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.store_id', $storeId)
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereIn('sale_items.product_id', $productIds)
            ->where('sales.sale_date', '>=', $anchor->copy()->subDays(6)->toDateString())
            ->where('sales.sale_date', '<=', $anchor->toDateString())
            ->selectRaw('sale_items.product_id, DATE(sales.sale_date) as d, CAST(SUM(sale_items.quantity) AS DECIMAL(15,4)) as qty')
            ->groupBy('sale_items.product_id', 'd')
            ->get();

        // Build [productId => [day0 qty, day1 qty, ..., day6 qty]] (oldest → newest)
        $trends = [];
        foreach ($productIds as $pid) {
            $trends[$pid] = array_fill(0, 7, 0.0);
        }

        foreach ($rows as $row) {
            $daysAgo = (int) $anchor->copy()->startOfDay()->diffInDays(
                Carbon::parse($row->d)->startOfDay(),
                absolute: false
            );
            // daysAgo is 0 (today) or negative; index = 6 + daysAgo (0=6 days ago, 6=today)
            $idx = 6 + $daysAgo;
            if ($idx >= 0 && $idx <= 6 && isset($trends[$row->product_id])) {
                $trends[$row->product_id][$idx] = (float) $row->qty;
            }
        }

        return $trends;
    }
}
