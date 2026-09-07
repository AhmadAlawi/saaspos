{{-- Print-only template rendered by ReportPdfRenderer (mPDF). Plain, high-contrast
     table styling — mPDF supports a limited CSS subset, so keep it inline & simple. --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: dejavusans, sans-serif; color: #0f172a; font-size: 9px; }
        h1 { font-size: 15px; margin: 0 0 2px 0; }
        .meta { color: #64748b; font-size: 9px; margin: 0 0 10px 0; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data thead th {
            background: #f1f5f9; color: #334155; text-align: {{ $rtl ? 'right' : 'left' }};
            font-size: 8.5px; text-transform: uppercase; letter-spacing: .3px;
            padding: 5px 6px; border-bottom: 1px solid #cbd5e1;
        }
        table.data tbody td {
            padding: 4px 6px; border-bottom: 1px solid #eef2f7; font-size: 9px;
        }
        table.data tbody tr:nth-child(even) td { background: #f8fafc; }
        /* Numeric-looking columns lean the other way for readability. */
        table.data td.num, table.data th.num { text-align: {{ $rtl ? 'left' : 'right' }}; }
        .empty { color: #94a3b8; padding: 16px 0; text-align: center; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p class="meta">{{ __('reports.filter.from') }}: {{ $from }} &nbsp;&middot;&nbsp; {{ __('reports.filter.to') }}: {{ $to }}</p>

    @if (empty($rows))
        <div class="empty">{{ __('reports.pdf.no_rows') }}</div>
    @else
        <table class="data">
            <thead>
                <tr>
                    @foreach ($header as $col)
                        <th>{{ $col }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td class="{{ is_numeric(str_replace([',', '.', '-', '%'], '', (string) $cell)) && $cell !== '' ? 'num' : '' }}">{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
