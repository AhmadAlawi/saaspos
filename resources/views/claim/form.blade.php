<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('claim.form.title') }}</title>
    <style>
        body { font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 24px 16px; }
        .claim-card { max-width: 380px; margin: 0 auto; background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,.06); }
        .claim-title { font-size: 1.25rem; font-weight: 700; margin: 0 0 6px; }
        .claim-sub { color: #666; font-size: .9rem; margin: 0 0 20px; }
        .claim-field { display: block; margin-bottom: 14px; }
        .claim-field span { display: block; font-size: .8rem; font-weight: 600; margin-bottom: 4px; }
        .claim-field input { width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; }
        .claim-btn { width: 100%; padding: 12px; border: none; border-radius: 8px; background: #141414; color: #fff; font-size: 1rem; font-weight: 600; cursor: pointer; }
        .claim-total { text-align: center; margin-bottom: 18px; color: #444; }
        .claim-errors { background: #fee; color: #900; border-radius: 8px; padding: 10px 12px; margin-bottom: 14px; font-size: .85rem; }
    </style>
</head>
<body>
    <div class="claim-card">
        <div class="claim-title">{{ __('claim.form.title') }}</div>
        <div class="claim-sub">{{ __('claim.form.sub') }}</div>

        <div class="claim-total">{{ __('claim.form.sale_number', ['number' => $sale->number]) }}</div>

        @if ($errors->any())
            <div class="claim-errors">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('sale.claim.store', ['token' => $token]) }}">
            @csrf
            <label class="claim-field">
                <span>{{ __('claim.form.name') }}</span>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="191">
            </label>
            <label class="claim-field">
                <span>{{ __('claim.form.phone') }}</span>
                <input type="tel" name="phone" value="{{ old('phone') }}" required maxlength="32">
            </label>
            <button type="submit" class="claim-btn">{{ __('claim.form.submit') }}</button>
        </form>
    </div>
</body>
</html>
