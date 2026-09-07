@if ($company->receipt_show_logo && $company->logo_url)
    <div class="rcpt-logo-img"><img src="{{ $company->logo_url }}" alt=""></div>
@elseif ($company->receipt_show_logo)
    <div class="rcpt-logo">{{ $company->display_app_name }}</div>
@endif
