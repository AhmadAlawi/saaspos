<x-admin-layout
    active="journal"
    :title="$entry->number"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('accounting.nav.section')],
        ['label' => __('accounting.journal.title'), 'href' => route('admin.accounting.journal.index')],
        ['label' => $entry->number],
    ]">

    <div class="page-wide">
        @if (session('status'))
            <x-alert type="success" class="mb-4">{{ session('status') }}</x-alert>
        @endif
        @error('reverse')
            <x-alert type="danger" class="mb-4">{{ $message }}</x-alert>
        @enderror

        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ $entry->number }}</h1>
                <p class="page-sub">
                    <x-accounting.source-badge :source="$entry->source" />
                    {{ $entry->entry_date?->format('d M Y') }}
                    @if ($entry->description) · {{ $entry->description }} @endif
                </p>
            </div>
            @if (! $entry->reversed_by_id && $entry->source !== \App\Models\JournalEntry::SOURCE_SALE_VOID && auth()->user()?->hasPermission('accounting.reverse_entry'))
                <button type="button"
                        x-data
                        @click="$store.confirm.show({
                            title: {{ Js::from(__('accounting.journal.reverse')) }},
                            message: {{ Js::from(__('accounting.journal.reverse_confirm')) }},
                            intent: 'danger',
                            confirmLabel: {{ Js::from(__('accounting.journal.reverse')) }},
                            cancelLabel: {{ Js::from(__('accounting.cancel')) }},
                            onConfirm: () => $submitForm({{ Js::from(route('admin.accounting.journal.reverse', $entry)) }}),
                        })"
                        class="pos-btn pos-btn-sm pos-btn-ghost">
                    <x-icon name="refund" class="w-4 h-4" />
                    {{ __('accounting.journal.reverse') }}
                </button>
            @endif
        </div>

        @if ($entry->reversedBy)
            <x-alert type="warning" class="mb-4">
                {{ __('accounting.journal.reversed_by') }}
                <a href="{{ route('admin.accounting.journal.show', $entry->reversedBy) }}" class="link">{{ $entry->reversedBy->number }}</a>
            </x-alert>
        @endif
        @if ($entry->reversalOf)
            <x-alert type="info" class="mb-4">
                {{ __('accounting.journal.reversal_of') }}
                <a href="{{ route('admin.accounting.journal.show', $entry->reversalOf) }}" class="link">{{ $entry->reversalOf->number }}</a>
            </x-alert>
        @endif

        <div class="card card-pad-0">
            <div class="dt-scroll">
            <table class="dt-table">
                <thead>
                    <tr>
                        <th>{{ __('accounting.journal.columns.account') }}</th>
                        <th>{{ __('accounting.journal.columns.line_description') }}</th>
                        <th class="num">{{ __('accounting.journal.columns.debit') }}</th>
                        <th class="num">{{ __('accounting.journal.columns.credit') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entry->lines as $line)
                        <tr>
                            <td><span class="mono fg-tertiary">{{ $line->account->code }}</span> {{ $line->account->name }}</td>
                            <td class="fg-tertiary">{{ $line->description }}</td>
                            <td class="num tnum">{{ bccomp((string) $line->debit, '0', 4) > 0 ? format_money($line->debit) : '' }}</td>
                            <td class="num tnum">{{ bccomp((string) $line->credit, '0', 4) > 0 ? format_money($line->credit) : '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="dt-total-row font-semibold">
                        <td colspan="2">{{ __('reports.totals_row') }}</td>
                        <td class="num tnum">{{ format_money($entry->lines->sum('debit')) }}</td>
                        <td class="num tnum">{{ format_money($entry->lines->sum('credit')) }}</td>
                    </tr>
                </tfoot>
            </table>
            </div>
        </div>

        <p class="text-xs fg-tertiary mt-3">
            {{ __('accounting.journal.posted_by', [
                'user' => $entry->createdBy?->name ?? '—',
                'at'   => $entry->posted_at?->format('d M Y H:i') ?? '',
            ]) }}
        </p>
    </div>
</x-admin-layout>
