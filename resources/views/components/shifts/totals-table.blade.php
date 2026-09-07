@props(['totals'])

{{-- Shared display of a shift's totals — drives the close form preview,
     the show page's X/Z panel, and the eventual thermal Z-report. --}}
<div class="space-y-4">
    {{-- Sales summary --}}
    <div>
        <div class="text-xs uppercase tracking-wide text-muted mb-2">{{ __('shifts.sections.sales_summary') }}</div>
        <table class="dt-table dt-table-compact">
            <tbody>
                <tr>
                    <td>{{ __('shifts.totals.sales_count') }}</td>
                    <td class="num tnum">{{ $totals['sales_count'] }}</td>
                </tr>
                <tr>
                    <td>{{ __('shifts.totals.sales_total') }}</td>
                    <td class="num tnum">{{ format_money($totals['sales_total']) }}</td>
                </tr>
                <tr>
                    <td>{{ __('shifts.totals.tax_total') }}</td>
                    <td class="num tnum">{{ format_money($totals['tax_total']) }}</td>
                </tr>
                <tr>
                    <td>{{ __('shifts.totals.discount_total') }}</td>
                    <td class="num tnum">{{ format_money($totals['discount_total']) }}</td>
                </tr>
                <tr>
                    <td>{{ __('shifts.totals.refunds_count') }}</td>
                    <td class="num tnum">{{ $totals['refunds_count'] }}</td>
                </tr>
                <tr>
                    <td>{{ __('shifts.totals.refunds_total') }}</td>
                    <td class="num tnum">{{ format_money($totals['refunds_total']) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Payments breakdown --}}
    <div>
        <div class="text-xs uppercase tracking-wide text-muted mb-2">{{ __('shifts.sections.payments_received') }}</div>
        <table class="dt-table dt-table-compact">
            <tbody>
                @foreach (collect($totals['payment_totals'])->sortBy('name') as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td class="num tnum">{{ format_money($row['amount']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Cash drawer --}}
    <div>
        <div class="text-xs uppercase tracking-wide text-muted mb-2">{{ __('shifts.sections.cash_drawer') }}</div>
        <table class="dt-table dt-table-compact">
            <tbody>
                <tr>
                    <td>{{ __('shifts.totals.opening_cash') }}</td>
                    <td class="num tnum">{{ format_money($totals['opening_cash']) }}</td>
                </tr>
                <tr>
                    <td>{{ __('shifts.totals.cash_sales') }}</td>
                    <td class="num tnum">{{ format_money($totals['cash_sales']) }}</td>
                </tr>
                <tr>
                    <td>{{ __('shifts.totals.cash_refunds') }}</td>
                    <td class="num tnum">−{{ format_money($totals['cash_refunds']) }}</td>
                </tr>
                <tr>
                    <td>{{ __('shifts.totals.pay_ins') }}</td>
                    <td class="num tnum">{{ format_money($totals['pay_ins']) }}</td>
                </tr>
                <tr>
                    <td>{{ __('shifts.totals.pay_outs') }}</td>
                    <td class="num tnum">−{{ format_money($totals['pay_outs']) }}</td>
                </tr>
                {{-- Supplier payments made from the till, listed per supplier so
                     the cash-up shows who was paid — not just a lump pay-out. --}}
                @if (! empty($totals['supplier_payouts']))
                    <tr class="text-muted">
                        <td class="ps-4" colspan="2">{{ __('shifts.totals.supplier_payments_heading') }}</td>
                    </tr>
                    @foreach ($totals['supplier_payouts'] as $sp)
                        <tr class="text-muted">
                            <td class="ps-6">{{ $sp['supplier'] }}</td>
                            <td class="num tnum">−{{ format_money($sp['amount']) }}</td>
                        </tr>
                    @endforeach
                @endif
                <tr class="font-semibold">
                    <td>{{ __('shifts.totals.expected_cash') }}</td>
                    <td class="num tnum">{{ format_money($totals['expected_cash']) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
