{{-- Store identity + sale meta (number/date/customer/cashier) — bundled
     as one structural block, same pairing as the ESC/POS side's
     blockStoreInfo(), since an admin reordering blocks would never want
     the sale number separated from the store name that identifies it. --}}
<div class="rcpt-store">
    <div class="rcpt-store-name">{{ $sale->store->name }}</div>
    @foreach ($sale->store->address_lines as $line)
        <div class="rcpt-store-addr">{{ $line }}</div>
    @endforeach
    @if ($sale->store->phone)
        <div class="rcpt-store-addr">{{ $sale->store->phone }}</div>
    @endif
    @if ($company->tax_registration_number)
        <div class="rcpt-store-tax">{{ __('sales.receipt.gstin') }}: {{ $company->tax_registration_number }}</div>
    @endif
</div>

<div class="rcpt-meta">
    <div><strong>{{ $sale->number }}</strong></div>
    <div>{{ format_datetime($sale->sale_datetime ?? $sale->created_at) }}</div>
</div>

@if ($company->receipt_show_customer && $sale->customer)
    <div class="rcpt-line">
        {{ __('sales.receipt.customer') }}: {{ $sale->customer->name }}
        @if ($sale->customer->phone)
            @php $ph = (string) $sale->customer->phone; @endphp
            · {{ $public && mb_strlen($ph) > 4 ? '••••'.mb_substr($ph, -4) : $ph }}
        @endif
    </div>
@endif
@if ($company->receipt_show_cashier && $sale->cashier)
    <div class="rcpt-line">{{ __('sales.receipt.cashier') }}: {{ $sale->cashier->name }}</div>
@endif

<div class="rcpt-rule"></div>
