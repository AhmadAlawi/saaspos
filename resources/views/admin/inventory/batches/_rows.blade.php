{{--
    Table-view rows for the Batches list.

    Rendered inline by `admin.inventory.batches.index` (first page) and standalone
    by `ProductBatchController@rows` (JSON `html`) for every subsequent page /
    filter / search.

    Read-only except for Archive, which only appears on an EMPTY batch — the
    affordance is rendered conditionally rather than rendered-and-disabled, the
    same idiom the adjustments list uses for its delete.

    Expects: $batches (iterable of ProductBatch, with store/product/variant loaded).
--}}
@php $today = now()->startOfDay(); @endphp
@foreach ($batches as $batch)
    @php
        $isExpired      = $batch->isExpired();
        $isExpiringSoon = $batch->isExpiringSoon(30);
        $daysToExpiry   = $batch->expiry_date
            ? $today->diffInDays($batch->expiry_date->startOfDay(), false)
            : null;
    @endphp
    <tr class="prod-row" data-dt-row
        data-dt-id="{{ $batch->id }}"
        data-dt-name="{{ $batch->batch_number . ' ' . ($batch->product?->name) }}">
        <td>
            <div class="font-medium">{{ $batch->product?->name ?? '—' }}</div>
            <div class="fg-tertiary text-xs mono">
                {{ $batch->product?->sku ?? '—' }}
                @if ($batch->variant?->sku)
                    · {{ $batch->variant->sku }}
                @endif
            </div>
        </td>
        <td>{{ $batch->store?->name ?? '—' }}</td>
        <td class="mono">{{ $batch->batch_number }}</td>
        <td>{{ $batch->manufacture_date?->toDateString() ?: '—' }}</td>
        <td>
            @if ($batch->expiry_date)
                <span>{{ $batch->expiry_date->toDateString() }}</span>
                @if ($daysToExpiry !== null)
                    <span class="fg-tertiary text-xs ms-1">
                        @if ($daysToExpiry < 0)
                            {{ __('batches.expiry.expired_n_days', ['n' => abs($daysToExpiry)]) }}
                        @elseif ($daysToExpiry === 0)
                            {{ __('batches.expiry.today') }}
                        @else
                            {{ __('batches.expiry.in_n_days', ['n' => $daysToExpiry]) }}
                        @endif
                    </span>
                @endif
            @else
                <span class="fg-tertiary">—</span>
            @endif
        </td>
        <td class="num tnum">{{ rtrim(rtrim((string) $batch->quantity, '0'), '.') }}</td>
        <td>
            {{-- Archived wins over expiry: on the Archived tab the one thing
                 the reader needs to know is that this row isn't in play. --}}
            @if ($batch->trashed())
                <span class="prod-badge prod-badge-muted">{{ __('batches.status.archived') }}</span>
            @elseif ($isExpired)
                <span class="prod-badge prod-badge-danger">{{ __('batches.status.expired') }}</span>
            @elseif ($isExpiringSoon)
                <span class="prod-badge prod-badge-warning">{{ __('batches.status.expiring_soon') }}</span>
            @else
                <span class="prod-badge prod-badge-positive">{{ __('batches.status.live') }}</span>
            @endif
        </td>
        <td class="dt-actions-col">
            @if ($batch->trashed())
                {{-- Restore is never destructive — the row never left the table
                     and archiving only ever removes an empty batch — so it goes
                     straight through with no confirmation. --}}
                @can('restore', $batch)
                    <x-admin.row-actions>
                        <x-admin.row-action icon="refresh" :label="__('batches.actions.restore')"
                            @click="$submitForm({{ \Illuminate\Support\Js::from(route('admin.inventory.batches.restore', $batch->id)) }})" />
                    </x-admin.row-actions>
                @endcan
            @else
                {{-- Only an EMPTY batch can be archived: archiving one that still
                     holds stock would strand that quantity in the store's stock
                     level with no batch behind it. To clear a batch that has stock,
                     adjust it out first. --}}
                @can('delete', $batch)
                    <x-admin.row-actions>
                        <x-admin.row-action icon="trash" variant="danger" :label="__('batches.actions.archive')"
                            @click="$store.confirm.show({
                                title: {{ \Illuminate\Support\Js::from(__('batches.confirm_archive.title', ['batch' => $batch->batch_number])) }},
                                message: {{ \Illuminate\Support\Js::from(__('batches.confirm_archive.message')) }},
                                intent: 'danger',
                                confirmLabel: {{ \Illuminate\Support\Js::from(__('batches.actions.archive')) }},
                                onConfirm: () => $deleteForm({{ \Illuminate\Support\Js::from(route('admin.inventory.batches.destroy', $batch)) }}),
                            })" />
                    </x-admin.row-actions>
                @endcan
            @endif
        </td>
    </tr>
@endforeach
