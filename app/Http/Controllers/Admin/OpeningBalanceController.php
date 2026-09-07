<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Accounting\PostOpeningBalances;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OpeningBalanceRequest;
use App\Models\Account;
use App\Models\JournalEntry;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Opening balances (docs/features/accounting.md §9). A one-time migration
 * screen: enter each account's starting balance on its natural side and the
 * Opening Balance Equity account (3090) plugs the difference. Editable only
 * until the first real transaction posts. Gated by `accounting.opening_balances`
 * (typically super admin, used once).
 */
class OpeningBalanceController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('accounting.opening_balances'), 403);

        $accounts = Account::query()
            ->whereIn('type', ['asset', 'liability', 'equity'])
            ->where('is_active', true)
            ->where('code', '!=', '3090')            // the contra plug, not entered directly
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        $existing = $this->existingEntry();

        return view('admin.accounting.opening-balances.index', [
            'groups'      => $accounts->groupBy('type')->sortBy(
                fn ($g, $type) => array_search($type, ['asset', 'liability', 'equity'], true),
            ),
            'prefill'     => $this->prefillFrom($existing),
            'entryDate'   => $existing?->entry_date?->toDateString() ?? CarbonImmutable::today()->toDateString(),
            'canEdit'     => ! $this->hasRealTransactions(),
            'hasExisting' => $existing !== null,
        ]);
    }

    public function store(OpeningBalanceRequest $request, PostOpeningBalances $post): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('accounting.opening_balances'), 403);

        if ($this->hasRealTransactions()) {
            $message = __('accounting.opening.errors.locked');
            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        $balances = collect($request->validated('balances') ?? [])
            ->filter(fn ($v) => $v !== null && $v !== '' && (float) $v > 0)
            ->all();

        $post([
            'store_id'   => (int) ($request->validated('store_id') ?? current_store_id() ?? 0),
            'entry_date' => $request->validated('entry_date'),
            'balances'   => $balances,
        ]);

        $message = __('accounting.opening.flash.posted');

        if ($request->wantsJson()) {
            return response()->json([
                'message'  => $message,
                'redirect' => route('admin.accounting.opening-balances.index'),
            ]);
        }

        return redirect()
            ->route('admin.accounting.opening-balances.index')
            ->with('success', $message);
    }

    // ── Internals ──────────────────────────────────────────────────────

    /** A real transaction is any posted entry that isn't the opening one. */
    private function hasRealTransactions(): bool
    {
        return JournalEntry::query()
            ->where('is_posted', true)
            ->where('source', '!=', JournalEntry::SOURCE_OPENING_BALANCE)
            ->exists();
    }

    private function existingEntry(): ?JournalEntry
    {
        return JournalEntry::query()
            ->where('source', JournalEntry::SOURCE_OPENING_BALANCE)
            ->with('lines')
            ->latest('id')
            ->first();
    }

    /** @return array<int, string>  account_id => natural-side amount */
    private function prefillFrom(?JournalEntry $entry): array
    {
        if (! $entry) {
            return [];
        }

        return $entry->lines
            ->mapWithKeys(function ($line) {
                $amount = bccomp((string) $line->debit, '0', 4) > 0 ? $line->debit : $line->credit;

                return [(int) $line->account_id => rtrim(rtrim(number_format((float) $amount, 4, '.', ''), '0'), '.')];
            })
            ->all();
    }
}
