{{--
    Scheduled-report delivery email. Standalone HTML with inline styles —
    email clients don't load external CSS, so the project's no-inline-CSS rule
    (which targets the app UI) doesn't apply here.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $reportTitle }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="520" cellpadding="0" cellspacing="0" style="width:520px;max-width:92%;background:#ffffff;border:1px solid #e6e8eb;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="padding:20px 24px;border-bottom:1px solid #eef0f2;">
                            <div style="font-size:16px;font-weight:600;color:#1c1e21;">{{ $appName }}</div>
                            <div style="font-size:12.5px;color:#6b7280;margin-top:2px;">{{ __('reports.schedule.email.heading') }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:22px 24px;">
                            <p style="margin:0 0 14px;font-size:13.5px;line-height:1.6;color:#374151;">
                                @if ($body)
                                    {!! nl2br(e($body)) !!}
                                @else
                                    {{ __('reports.schedule.email.body', ['report' => $reportTitle]) }}
                                @endif
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8f9fb;border:1px solid #eef0f2;border-radius:8px;">
                                <tr>
                                    <td style="padding:12px 14px;font-size:12.5px;color:#6b7280;width:120px;">{{ __('reports.schedule.email.report') }}</td>
                                    <td style="padding:12px 14px;font-size:12.5px;color:#1c1e21;font-weight:600;">{{ $reportTitle }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:0 14px 12px;font-size:12.5px;color:#6b7280;">{{ __('reports.schedule.email.file') }}</td>
                                    <td style="padding:0 14px 12px;font-size:12.5px;color:#1c1e21;font-weight:600;word-break:break-all;">{{ $filename }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:14px 24px;border-top:1px solid #eef0f2;font-size:11px;color:#9ca3af;">
                            {{ __('reports.schedule.email.footer', ['app' => $appName, 'schedule' => $scheduleName]) }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
