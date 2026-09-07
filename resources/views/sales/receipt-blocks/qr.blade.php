@if ($company->receipt_show_qr && $receiptUrl)
    <div class="rcpt-qr">
        <x-receipt-qr :url="$receiptUrl" />
        <div class="rcpt-qr-caption">{{ __('sales.receipt.qr_caption') }}</div>
    </div>
@endif
