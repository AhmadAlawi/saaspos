<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Accounting\PostJournalEntry;
use App\Actions\Accounting\ReverseJournalEntry;
use App\Exceptions\PeriodLockedException;
use App\Http\Controllers\Admin\Concerns\ResolvesReportFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreManualJournalEntryRequest;
use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The journal / day book (docs/features/accounting.md §5, §6.2, §7.6). Lists
 * posted entries, shows one entry's lines, posts manual entries, and reverses a
 * posted entry. Viewing needs `accounting.view`; posting needs
 * `accounting.manual_entry`; reversing needs `accounting.reverse_entry`.
 */
class JournalEntryController extends Controller
{
    use ResolvesReportFilters;

    public function __construct(
        private PostJournalEntry $post,
        private ReverseJournalEntry $reverse,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('accounting.view'), 403);

        [$from, $to, $storeId, $period] = $this->reportFilters($request);
        $source = (string) $request->query('source', '');

        $entries = JournalEntry::query()
            ->posted()
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->whereDate('entry_date', '>=', $from->toDateString())
            ->whereDate('entry_date', '<=', $to->toDateString())
            ->when($source !== '', fn ($q) => $q->where('source', $source))
            ->withSum('lines', 'debit')
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->get();

        return view('admin.accounting.journal.index', [
            'entries' => $entries,
            'from'    => $from,
            'to'      => $to,
            'storeId' => $storeId,
            'period'  => $period,
            'source'  => $source,
            'sources' => JournalEntry::SOURCES ?? [],
            'stores'  => accessible_stores(),
        ]);
    }

    public function show(Request $request, JournalEntry $entry): View
    {
        abort_unless($request->user()?->hasPermission('accounting.view'), 403);

        $entry->load(['lines.account', 'store', 'createdBy', 'reversalOf', 'reversedBy']);

        return view('admin.accounting.journal.show', ['entry' => $entry]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('accounting.manual_entry'), 403);

        return view('admin.accounting.journal.create', [
            'accounts' => Account::active()->orderBy('code')->get(['id', 'code', 'name']),
            'stores'   => accessible_stores(),
        ]);
    }

    public function store(StoreManualJournalEntryRequest $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('accounting.manual_entry'), 403);

        $lines = collect($request->input('lines'))
            ->map(fn ($l) => [
                'account_id'  => (int) $l['account_id'],
                'debit'       => $l['debit'] ?: 0,
                'credit'      => $l['credit'] ?: 0,
                'description' => $l['description'] ?? null,
            ])->all();

        try {
            $entry = ($this->post)([
                'store_id'    => (int) ($request->input('store_id') ?: current_store_id()),
                'entry_date'  => $request->input('entry_date'),
                'source'      => JournalEntry::SOURCE_MANUAL,
                'description' => $request->input('description'),
                'created_by'  => $request->user()->id,
                'lines'       => $lines,
            ]);
        } catch (PeriodLockedException) {
            return back()->withInput()->withErrors(['entry_date' => __('accounting.journal.period_locked')]);
        }

        return redirect()
            ->route('admin.accounting.journal.show', $entry)
            ->with('status', __('accounting.journal.posted'));
    }

    public function reverse(Request $request, JournalEntry $entry): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('accounting.reverse_entry'), 403);
        abort_unless($entry->is_posted, 404);

        $fail = function (string $message) use ($request): JsonResponse|RedirectResponse {
            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->withErrors(['reverse' => $message]);
        };

        if ($entry->reversed_by_id) {
            return $fail(__('accounting.journal.already_reversed'));
        }

        try {
            $reversal = ($this->reverse)($entry, JournalEntry::SOURCE_MANUAL);
        } catch (PeriodLockedException) {
            return $fail(__('accounting.journal.period_locked'));
        }

        $redirect = route('admin.accounting.journal.show', $reversal);
        $message  = __('accounting.journal.reversed');

        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'redirect' => $redirect]);
        }

        return redirect($redirect)->with('status', $message);
    }
}
