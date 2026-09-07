{{--
    Floating "Buy now" call-to-action shown only when the install is
    running in demo mode (POS_DEMO_MODE=true). Links to the CodeCanyon
    purchase URL configured at config('pos.demo.purchase_url') — the whole
    point of the public demo is to convert visitors. Hidden on real installs.
--}}
@if (pos_is_demo() && config('pos.demo.purchase_url'))
    <a href="{{ config('pos.demo.purchase_url') }}"
       target="_blank"
       rel="noopener"
       class="demo-buy-now"
       aria-label="{{ __('admin.demo.buy_now') }}">
        <x-icon name="cart" class="w-[18px] h-[18px]" />
        <span class="demo-buy-now-label">{{ __('admin.demo.buy_now') }}</span>
    </a>
@endif
