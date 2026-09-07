@props(['cards' => []])

{{--
    A responsive row of summary/KPI cards shown at the top of a list page
    (Sales, Purchases, Customers, …). Each card is a small associative array:

        ['label' => 'Total sales', 'value' => format_money($x), 'sub' => '12 today', 'tone' => 'positive']

    `tone` is optional: positive | warning | danger (colours the value).

    The whole row is wrapped in `[data-summary-cards]` so the filter handlers
    (lib/inv-filter-ajax.js, and the self-managed Customers/Suppliers pages)
    can swap its innerHTML when filters change — the server recomputes the
    numbers from the same filtered query as the table.
--}}
<div class="summary-cards" data-summary-cards>
    @foreach ($cards as $card)
        <div class="summary-card @if (!empty($card['tone'])) summary-card--{{ $card['tone'] }} @endif">
            <div class="summary-card-label">{{ $card['label'] }}</div>
            {{-- `key` lets client-side pages (Products/Shifts filter in JS)
                 target the value span and recompute it from visible rows. --}}
            <div class="summary-card-value tnum" @isset($card['key']) data-card-value="{{ $card['key'] }}" @endisset>{{ $card['value'] }}</div>
            @if (!empty($card['sub']))
                <div class="summary-card-sub">{{ $card['sub'] }}</div>
            @endif
        </div>
    @endforeach
</div>
