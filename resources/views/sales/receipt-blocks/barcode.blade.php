@if ($company->receipt_show_barcode)
    <x-receipt-barcode :value="$sale->number" />
    <div class="rcpt-barcode-number mono">{{ $sale->number }}</div>
@endif
