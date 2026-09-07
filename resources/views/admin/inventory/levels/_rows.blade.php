{{--
    Table-view rows for the Stock levels list.

    Rendered inline by `admin.inventory.levels.index` (first page) and standalone
    by `StockLevelController@rows` (JSON `html`) for every subsequent page /
    filter / search. Each row carries an inline reorder-override edit
    (`data-ajax-form`), which submits independently of pagination.

    Expects: $levels (iterable of StockLevel, with product/variant/store loaded).
--}}
@foreach ($levels as $level)
    @php
        $product    = $level->product;
        $variant    = $level->variant;
        $qty        = (float) $level->quantity;
        $reorder    = $level->reorder_level_override ?? $product?->reorder_level;
        $isLow      = $reorder !== null && $qty <= (float) $reorder;
        $isNegative = $qty < 0;
    @endphp
    <tr data-dt-row
        data-dt-name="{{ $product?->name ?? '' }}"
        data-dt-id="{{ $level->id }}">
        <td>
            <div class="store-row-name-line">
                <span class="store-row-name">{{ $product?->name ?? '—' }}</span>
                @if ($variant) <span class="fg-tertiary">· {{ $variant->label }}</span> @endif
                @if ($isNegative)
                    <span class="prod-badge prod-badge-danger">{{ __('inventory.levels.badges.negative') }}</span>
                @elseif ($isLow)
                    <span class="prod-badge prod-badge-warning">{{ __('inventory.levels.badges.low') }}</span>
                @endif
            </div>
        </td>
        <td class="mono">{{ $variant?->sku ?? $product?->sku }}</td>
        <td>{{ $level->store?->name }}</td>
        <td class="num tnum">{{ rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.') ?: '0' }}</td>
        <td class="num tnum fg-tertiary">{{ rtrim(rtrim(number_format((float) $level->reserved_quantity, 4, '.', ''), '0'), '.') ?: '0' }}</td>
        {{-- Weighted-average cost — updated by every positive costed movement. --}}
        <td class="num tnum">{{ (float) $level->weighted_average_cost > 0 ? format_money($level->weighted_average_cost) : '—' }}</td>
        <td class="num">
            <form method="POST" action="{{ route('admin.inventory.levels.reorder', $level) }}" class="inline-flex" data-ajax-form>
                @csrf
                @method('PATCH')
                <input type="number" name="reorder_level_override"
                       value="{{ $level->reorder_level_override !== null ? rtrim(rtrim(number_format((float) $level->reorder_level_override, 4, '.', ''), '0'), '.') : '' }}"
                       step="any" min="0"
                       class="pos-input pos-input-sm inv-reorder-input tnum"
                       placeholder="{{ $product?->reorder_level !== null ? number_format((float) $product->reorder_level, 0) : '—' }}"
                       @change="$el.form.requestSubmit()">
            </form>
        </td>
        <td>{{ $level->last_movement_at?->diffForHumans() ?? '—' }}</td>
    </tr>
@endforeach
