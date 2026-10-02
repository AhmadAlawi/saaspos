@props(['appleUrl' => null, 'googleUrl' => null])
@if ($appleUrl || $googleUrl)
    <div class="wallet-buttons" data-wallet-buttons>
        @if ($appleUrl)
            <a href="{{ $appleUrl }}" class="wallet-btn wallet-btn-apple" data-wallet="apple">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">
                    <path d="M16.365 1.43c0 1.14-.463 2.257-1.19 3.062-.79.88-2.09 1.56-3.13 1.47-.14-1.09.42-2.24 1.14-3.02.79-.87 2.16-1.51 3.18-1.51zM20.5 17.27c-.55 1.27-.82 1.84-1.53 2.96-.99 1.56-2.39 3.5-4.12 3.51-1.53.02-1.93-.99-4-1-2.07-.01-2.51 1.02-4.04 1-1.73-.02-3.06-1.78-4.05-3.34C.46 16.94-.6 12.4 1 9.36c.98-1.9 2.75-3.1 4.65-3.13 1.5-.03 2.9.99 3.82.99.91 0 2.62-1.22 4.42-1.04.75.03 2.87.3 4.24 2.26-.11.07-2.53 1.47-2.5 4.39.03 3.49 3.06 4.65 3.09 4.66-.02.08-.48 1.65-1.22 3.28z"/>
                </svg>
                <span class="wallet-btn-txt">
                    <span class="wallet-btn-sm">{{ __('wallet.buttons.apple_sm') }}</span>
                    <span class="wallet-btn-lg">{{ __('wallet.buttons.apple_lg') }}</span>
                </span>
            </a>
        @endif
        @if ($googleUrl)
            <a href="{{ $googleUrl }}" class="wallet-btn wallet-btn-google" data-wallet="google">
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.74 3.27-8.1z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.99.66-2.25 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.85A11 11 0 0 0 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.1a6.6 6.6 0 0 1 0-4.2V7.05H2.18a11 11 0 0 0 0 9.9z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1a11 11 0 0 0-9.82 6.05l3.66 2.85C6.71 7.3 9.14 5.38 12 5.38z"/>
                </svg>
                <span class="wallet-btn-txt">
                    <span class="wallet-btn-sm">{{ __('wallet.buttons.google_sm') }}</span>
                    <span class="wallet-btn-lg">{{ __('wallet.buttons.google_lg') }}</span>
                </span>
            </a>
        @endif
    </div>

    <style>
        .wallet-buttons { display: flex; flex-wrap: wrap; gap: 10px; margin: 12px 0; }
        .wallet-btn {
            display: inline-flex; align-items: center; gap: 8px;
            background: #000; color: #fff; text-decoration: none;
            border-radius: 8px; padding: 10px 16px; font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            line-height: 1.15; border: 1px solid #000;
        }
        .wallet-btn-google { background: #000; }
        .wallet-btn-txt { display: flex; flex-direction: column; }
        .wallet-btn-sm { font-size: 10px; opacity: .85; }
        .wallet-btn-lg { font-size: 14px; font-weight: 600; }
    </style>

    <script>
        (function () {
            var box = document.querySelector('[data-wallet-buttons]');
            if (!box) return;
            var ua = navigator.userAgent || '';
            var isAppleDevice = /iPhone|iPad|iPod|Macintosh/.test(ua) && !/Windows/.test(ua);
            var isAndroid = /Android/.test(ua);
            // Only hide a button when the device is UNAMBIGUOUSLY the other
            // platform — desktop / unknown UAs keep both buttons visible.
            if (isAppleDevice) {
                var g = box.querySelector('[data-wallet="google"]');
                if (g) g.style.display = 'none';
            } else if (isAndroid) {
                var a = box.querySelector('[data-wallet="apple"]');
                if (a) a.style.display = 'none';
            }
        })();
    </script>
@endif
