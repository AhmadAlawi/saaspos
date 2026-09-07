<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<title>{{ __('customer_statement.title', ['name' => $customer->name]) }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    /* Paper-style layout — usable for screen (window.print()) and email.
       No external CSS so the mailable renders standalone. */
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { background: #fff; color: #111827; font: 13px/1.4 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
    .page { max-width: 760px; margin: 0 auto; padding: 32px 28px; }
    .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; border-bottom: 2px solid #111827; padding-bottom: 16px; margin-bottom: 20px; }
    .biz { font-size: 12px; line-height: 1.5; color: #4b5563; }
    .biz .name { font-size: 16px; font-weight: 700; color: #111827; margin-bottom: 4px; }
    .doc { text-align: right; }
    .doc .label { font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; color: #6b7280; margin-bottom: 4px; }
    .doc .title { font-size: 22px; font-weight: 700; }
    .doc .range { font-size: 12px; color: #6b7280; margin-top: 2px; }

    .meta { display: flex; gap: 32px; margin-bottom: 24px; }
    .meta .col { flex: 1; }
    .meta .col .lbl { font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; color: #6b7280; margin-bottom: 4px; }
    .meta .col .val { font-size: 13px; color: #111827; }
    .meta .col .val.lg { font-size: 15px; font-weight: 600; }

    .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
    .summary .box { border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px 12px; }
    .summary .box .lbl { font-size: 10.5px; letter-spacing: 0.08em; text-transform: uppercase; color: #6b7280; margin-bottom: 4px; }
    .summary .box .val { font-size: 16px; font-weight: 700; }
    .summary .closing .val.owes { color: #b45309; }

    table { width: 100%; border-collapse: collapse; font-size: 12px; }
    th { text-align: left; background: #f9fafb; color: #374151; font-weight: 600; padding: 8px 10px; border-bottom: 1px solid #e5e7eb; }
    td { padding: 8px 10px; border-bottom: 1px solid #f3f4f6; }
    .num { text-align: right; font-variant-numeric: tabular-nums; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
    .badge { display: inline-block; font-size: 10.5px; padding: 2px 8px; border-radius: 999px; font-weight: 600; }
    .badge.sale    { background: #f3f4f6; color: #374151; }
    .badge.payment { background: #d1fae5; color: #047857; }
    .badge.refund  { background: #fef3c7; color: #92400e; }
    .badge.void    { background: #fee2e2; color: #b91c1c; }
    .badge.opening { background: #e5e7eb; color: #374151; }

    .closing-row td { background: #f9fafb; font-weight: 700; font-size: 13px; padding-top: 12px; padding-bottom: 12px; }
    .closing-row td:last-child.owes { color: #b45309; }

    .foot { margin-top: 24px; text-align: center; color: #6b7280; font-size: 11px; line-height: 1.6; }
    .foot .pay { font-size: 12px; color: #111827; }
    .toolbar { display: flex; justify-content: flex-end; gap: 8px; margin-bottom: 12px; }
    .btn { background: #111827; color: #fff; border: none; border-radius: 6px; padding: 8px 14px; font-size: 12.5px; cursor: pointer; }
    .btn.ghost { background: transparent; color: #111827; border: 1px solid #d1d5db; }

    @media print {
        .no-print { display: none !important; }
        .page { padding: 12px 0; max-width: none; }
    }
</style>
</head>
<body>
<div class="page">
    <div class="toolbar no-print">
        <button type="button" class="btn ghost" onclick="window.close()">{{ __('customer_statement.actions.close') }}</button>
        <button type="button" class="btn" onclick="window.print()">{{ __('customer_statement.actions.print') }}</button>
    </div>

    <div class="head">
        <div class="biz">
            <div class="name">{{ $company->name ?: config('app.name') }}</div>
            @if ($company->address_line1)<div>{{ $company->address_line1 }}</div>@endif
            @if ($company->address_line2)<div>{{ $company->address_line2 }}</div>@endif
            @if ($company->city || $company->state)<div>{{ trim(($company->city ?? '').', '.($company->state ?? ''), ', ') }} {{ $company->postal_code }}</div>@endif
            @if ($company->phone)<div>{{ $company->phone }}</div>@endif
            @if ($company->email)<div>{{ $company->display_email }}</div>@endif
        </div>
        <div class="doc">
            <div class="label">{{ __('customer_statement.crumb') }}</div>
            <div class="title">{{ __('customer_statement.heading') }}</div>
            <div class="range">{{ $from->format('Y-m-d') }} → {{ $to->format('Y-m-d') }}</div>
        </div>
    </div>

    <div class="meta">
        <div class="col">
            <div class="lbl">{{ __('customer_statement.print.billed_to') }}</div>
            <div class="val lg">{{ $customer->name }}</div>
            @if ($customer->code)<div class="val mono">{{ $customer->code }}</div>@endif
            @if ($customer->phone)<div class="val">{{ $customer->phone }}</div>@endif
            @if ($customer->email)<div class="val">{{ $customer->display_email }}</div>@endif
        </div>
        <div class="col">
            <div class="lbl">{{ __('customer_statement.print.issued') }}</div>
            <div class="val">{{ now()->format('Y-m-d') }}</div>
            <div class="lbl" style="margin-top: 10px">{{ __('customer_statement.print.period') }}</div>
            <div class="val">{{ $from->format('Y-m-d') }} → {{ $to->format('Y-m-d') }}</div>
        </div>
    </div>

    <div class="summary">
        <div class="box">
            <div class="lbl">{{ __('customer_statement.kpis.opening') }}</div>
            <div class="val">{{ format_money($opening_balance) }}</div>
        </div>
        <div class="box">
            <div class="lbl">{{ __('customer_statement.kpis.debits') }}</div>
            <div class="val">{{ format_money($debits_total) }}</div>
        </div>
        <div class="box">
            <div class="lbl">{{ __('customer_statement.kpis.credits') }}</div>
            <div class="val">{{ format_money($credits_total) }}</div>
        </div>
        <div class="box closing">
            <div class="lbl">{{ __('customer_statement.kpis.closing') }}</div>
            <div class="val @if (bccomp($closing_balance, '0', 4) > 0) owes @endif">{{ format_money($closing_balance) }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('customer_statement.columns.date') }}</th>
                <th>{{ __('customer_statement.columns.type') }}</th>
                <th>{{ __('customer_statement.columns.reference') }}</th>
                <th class="num">{{ __('customer_statement.columns.debit') }}</th>
                <th class="num">{{ __('customer_statement.columns.credit') }}</th>
                <th class="num">{{ __('customer_statement.columns.running') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $from->format('Y-m-d') }}</td>
                <td><span class="badge opening">{{ __('customer_statement.types.opening') }}</span></td>
                <td>—</td>
                <td class="num">—</td>
                <td class="num">—</td>
                <td class="num"><strong>{{ format_money($opening_balance) }}</strong></td>
            </tr>
            @foreach ($events as $event)
                <tr>
                    <td>{{ $event['date'] }}</td>
                    <td><span class="badge {{ $event['type'] }}">{{ __('customer_statement.types.'.$event['type']) }}</span></td>
                    <td class="mono">{{ $event['reference'] }}</td>
                    <td class="num">{{ bccomp($event['debit'], '0', 4) > 0 ? format_money($event['debit']) : '—' }}</td>
                    <td class="num">{{ bccomp($event['credit'], '0', 4) > 0 ? format_money($event['credit']) : '—' }}</td>
                    <td class="num"><strong>{{ format_money($event['running_balance']) }}</strong></td>
                </tr>
            @endforeach
            <tr class="closing-row">
                <td colspan="3">{{ __('customer_statement.print.closing_label') }}</td>
                <td class="num">{{ format_money($debits_total) }}</td>
                <td class="num">{{ format_money($credits_total) }}</td>
                <td class="num @if (bccomp($closing_balance, '0', 4) > 0) owes @endif">{{ format_money($closing_balance) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="foot">
        @if (bccomp($closing_balance, '0', 4) > 0)
            <div class="pay">{{ __('customer_statement.print.please_pay', ['amount' => format_money($closing_balance)]) }}</div>
        @endif
        @if ($company->footer_text)
            <div style="margin-top: 8px">{{ $company->footer_text }}</div>
        @endif
        <div style="margin-top: 12px">{{ __('customer_statement.print.footer_note') }}</div>
    </div>
</div>

@if (! empty($autoPrint))
<script>
    window.addEventListener('load', () => setTimeout(() => window.print(), 250));
</script>
@endif
</body>
</html>
