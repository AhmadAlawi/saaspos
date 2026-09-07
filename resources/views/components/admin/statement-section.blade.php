@props([
    'label',        // section heading, e.g. "INCOME"
    'rows',         // list of ['code','name','amount']
    'total',        // section subtotal (string amount)
    'totalLabel',   // subtotal label, e.g. "Total income"
])

{{-- One section of a financial statement: header, account lines, subtotal. --}}
<tr class="stmt-section-row"><td colspan="2">{{ $label }}</td></tr>
@foreach ($rows as $r)
    <tr class="stmt-acct-row">
        <td><span class="stmt-code mono">{{ $r['code'] }}</span>{{ $r['name'] }}</td>
        <td class="stmt-num">{{ format_money($r['amount']) }}</td>
    </tr>
@endforeach
<tr class="stmt-subtotal-row">
    <td>{{ $totalLabel }}</td>
    <td class="stmt-num">{{ format_money($total) }}</td>
</tr>
