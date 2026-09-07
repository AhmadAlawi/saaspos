@if ($company->receipt_show_hsn_summary)
    @php $hsnSummary = \App\Support\ReceiptHsnSummary::build($sale); @endphp
    @if (count($hsnSummary))
        <div class="rcpt-rule"></div>
        <div class="rcpt-hsn">
            <div class="rcpt-hsn-title">{{ __('sales.receipt.hsn_summary') }}</div>
            <div class="rcpt-hsn-head">
                <span>{{ __('sales.receipt.hsn_col_code') }}</span>
                <span>{{ __('sales.receipt.hsn_col_taxable') }}</span>
                <span>{{ __('sales.receipt.hsn_col_tax') }}</span>
            </div>
            @foreach ($hsnSummary as $row)
                <div class="rcpt-hsn-row">
                    <span>{{ $row['hsn'] }}</span>
                    <span>{{ format_money($row['taxable']) }}</span>
                    <span>{{ format_money($row['tax']) }}</span>
                </div>
                @foreach ($row['components'] as $c)
                    <div class="rcpt-hsn-comp">
                        <span>{{ $c['name'] }}</span>
                        <span>{{ format_money($c['amount']) }}</span>
                    </div>
                @endforeach
            @endforeach
        </div>
    @endif
@endif
