<?php

namespace App\Actions\Accounting;

use App\Exceptions\InvalidJournalLineException;
use App\Exceptions\PeriodLockedException;
use App\Exceptions\UnbalancedJournalException;
use App\Models\JournalEntry;
use App\Services\Accounting\FiscalPeriodResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The single canonical writer for the ledger. Every auto-posting action and
 * the manual-entry form funnels through here — nothing inserts into
 * `journal_entries` / `journal_lines` directly (docs/features/accounting.md §5.5).
 *
 * Guarantees on a returned entry: it is balanced (Σ debit = Σ credit), every
 * line is exactly one of debit-only / credit-only, it is filed under the
 * fiscal period for its date, and that period was not locked (unless `force`).
 * Header + lines are written in one transaction, so callers can wrap their own
 * operation and the JE in the same transaction — a sale either has its entry or
 * doesn't exist.
 *
 * Input shape:
 *   [
 *     'store_id'       => int,
 *     'entry_date'     => 'Y-m-d' | DateTimeInterface   (defaults to today),
 *     'source'         => JournalEntry::SOURCE_*,
 *     'reference_type' => ?string,  'reference_id' => ?int,
 *     'reversal_of_id' => ?int,     'description'  => ?string,
 *     'created_by'     => ?int,     'force'        => ?bool,
 *     'lines'          => [ ['account_id'=>int, 'debit'=>string|0, 'credit'=>string|0, 'description'=>?string], … ],
 *   ]
 */
class PostJournalEntry
{
    public function __construct(private FiscalPeriodResolver $periods) {}

    public function __invoke(array $data): JournalEntry
    {
        return DB::transaction(function () use ($data) {
            $entryDate = $this->entryDate($data['entry_date'] ?? null);
            $storeId   = (int) $data['store_id'];

            $period = $this->periods->forDate($entryDate);
            if ($period->is_locked && ! ($data['force'] ?? false)) {
                throw new PeriodLockedException("Fiscal period {$period->name} is locked.");
            }

            // Plugins may inject extra lines (e.g. gateway fees) before validation.
            $lines = apply_filters('journal_entry.lines', $this->normalizeLines($data['lines'] ?? []), $data);

            $this->assertValid($lines);

            do_action('journal_entry.before_post', $data);

            $entry = JournalEntry::create([
                'store_id'         => $storeId,
                'number'           => $this->nextNumber($entryDate),
                'entry_date'       => $entryDate->toDateString(),
                'fiscal_period_id' => $period->id,
                'source'           => $data['source'],
                'reference_type'   => $data['reference_type'] ?? null,
                'reference_id'     => $data['reference_id'] ?? null,
                'reversal_of_id'   => $data['reversal_of_id'] ?? null,
                'description'      => $data['description'] ?? null,
                'is_posted'        => false,
                'created_by'       => $data['created_by'] ?? auth()->id(),
            ]);

            $sort = 0;
            foreach ($lines as $line) {
                $entry->lines()->create([
                    'account_id'  => (int) $line['account_id'],
                    'debit'       => $line['debit'] ?? 0,
                    'credit'      => $line['credit'] ?? 0,
                    'description' => $line['description'] ?? null,
                    'sort_order'  => $sort++,
                ]);
            }

            $entry->forceFill(['is_posted' => true, 'posted_at' => now()])->save();

            do_action('journal_entry.after_post', $entry);

            return $entry->load('lines');
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function assertValid(array $lines): void
    {
        if (count($lines) < 2) {
            throw new InvalidJournalLineException('A journal entry needs at least two lines.');
        }

        $totalDebit  = '0';
        $totalCredit = '0';

        foreach ($lines as $line) {
            $debit  = (string) ($line['debit'] ?? 0);
            $credit = (string) ($line['credit'] ?? 0);

            $debitPositive  = bccomp($debit, '0', 4) > 0;
            $creditPositive = bccomp($credit, '0', 4) > 0;

            // Exactly one side must be positive — never both, never neither,
            // never a negative amount.
            if ($debitPositive === $creditPositive) {
                throw new InvalidJournalLineException('Each line must be exactly one of debit or credit.');
            }

            $totalDebit  = bcadd($totalDebit, $debit, 4);
            $totalCredit = bcadd($totalCredit, $credit, 4);
        }

        if (bccomp($totalDebit, $totalCredit, 4) !== 0) {
            throw new UnbalancedJournalException("Debits ({$totalDebit}) do not equal credits ({$totalCredit}).");
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function normalizeLines(array $lines): array
    {
        return array_values(array_map(fn ($line) => [
            'account_id'  => $line['account_id'] ?? null,
            'debit'       => $line['debit'] ?? 0,
            'credit'      => $line['credit'] ?? 0,
            'description' => $line['description'] ?? null,
        ], $lines));
    }

    /** `JE-YYYYMM-NNNNN`, sequential within the entry's month. */
    private function nextNumber(CarbonImmutable $entryDate): string
    {
        $prefix = 'JE-'.$entryDate->format('Ym').'-';

        $last = JournalEntry::where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    private function entryDate(mixed $raw): CarbonImmutable
    {
        if ($raw instanceof \DateTimeInterface) {
            return CarbonImmutable::parse($raw->format('Y-m-d'))->startOfDay();
        }
        if (is_string($raw) && $raw !== '') {
            return CarbonImmutable::parse($raw)->startOfDay();
        }

        return CarbonImmutable::now()->startOfDay();
    }
}
