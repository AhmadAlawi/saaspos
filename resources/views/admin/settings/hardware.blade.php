<x-admin-layout
    active="hardware"
    :title="__('hardware.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('hardware.crumb_parent')],
        ['label' => __('hardware.title')],
    ]">

    @php
        $modeLabels = [
            'webusb'        => __('hardware.modes.webusb'),
            'browser_print' => __('hardware.modes.browser_print'),
            'network'       => __('hardware.modes.network'),
            'none'          => __('hardware.modes.none'),
        ];
        $receiptLabel = $receiptMode ? ($modeLabels[$receiptMode] ?? $receiptMode) : __('hardware.modes.unconfigured');
        $receiptOk    = in_array($receiptMode, ['webusb', 'browser_print', 'network'], true);
        $drawerLabel  = $drawerMode === 'via_receipt_printer'
            ? __('hardware.drawer.via_printer')
            : __('hardware.drawer.unconfigured');
    @endphp

    <div class="page-wide"
         x-data="hardwareDiagnostics({ testPrintUrl: '{{ route('admin.settings.hardware.test-print') }}' })">

        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('hardware.title') }}</h1>
                <p class="page-sub">{{ __('hardware.sub') }}</p>
            </div>
        </div>

        @if (! $terminal)
            <div class="card mb-5">
                <div class="card-body hw-empty">
                    <span class="hw-empty-icon"><x-icon name="alert" class="w-5 h-5" /></span>
                    <div>
                        <div class="hw-empty-title">{{ __('hardware.no_terminal_title') }}</div>
                        <div class="hw-empty-sub">{!! __('hardware.no_terminal_sub', ['url' => route('admin.terminals.index')]) !!}</div>
                    </div>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header"><div>
                <div class="card-title">{{ __('hardware.devices.title') }}</div>
                <div class="card-title-sub">
                    @if ($terminal)
                        {{ __('hardware.devices.for_terminal', ['name' => $terminal->name]) }}
                    @else
                        {{ __('hardware.devices.sub') }}
                    @endif
                </div>
            </div></div>
            <div class="hw-board">
                {{-- Receipt printer --}}
                <div class="hw-row">
                    <span class="hw-icon"><x-icon name="printer" class="w-5 h-5" /></span>
                    <div class="hw-row-body">
                        <div class="hw-row-title">{{ __('hardware.devices.receipt_printer') }}</div>
                        <div class="hw-row-meta">
                            {{ $receiptLabel }}@if ($paperWidth) · {{ strtoupper($paperWidth) }}@endif
                        </div>
                    </div>
                    <span class="hw-status {{ $receiptOk ? 'hw-status-ok' : 'hw-status-muted' }}">{{ $receiptLabel }}</span>
                    @if ($canTest)
                        <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost"
                                @click="testPrint()" :disabled="testingPrint">
                            <svg x-show="testingPrint" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                            </svg>
                            {{ __('hardware.actions.test_print') }}
                        </button>
                    @endif
                </div>

                {{-- Cash drawer --}}
                <div class="hw-row">
                    <span class="hw-icon"><x-icon name="cash" class="w-5 h-5" /></span>
                    <div class="hw-row-body">
                        <div class="hw-row-title">{{ __('hardware.devices.cash_drawer') }}</div>
                        <div class="hw-row-meta">{{ $drawerLabel }}</div>
                    </div>
                    <span class="hw-status {{ $drawerMode === 'via_receipt_printer' ? 'hw-status-ok' : 'hw-status-muted' }}">{{ $drawerLabel }}</span>
                    @if ($canTest)
                        <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost"
                                @click="testDrawer()" :disabled="testingDrawer">
                            <svg x-show="testingDrawer" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                            </svg>
                            {{ __('hardware.actions.test_drawer') }}
                        </button>
                    @endif
                </div>

                {{-- Barcode scanner (HID) --}}
                <div class="hw-row">
                    <span class="hw-icon"><x-icon name="barcode" class="w-5 h-5" /></span>
                    <div class="hw-row-body">
                        <div class="hw-row-title">{{ __('hardware.devices.scanner') }}</div>
                        <div class="hw-row-meta">{{ __('hardware.devices.scanner_hint') }}</div>
                    </div>
                    <span class="hw-status hw-status-muted">{{ __('hardware.devices.scanner_status') }}</span>
                </div>

                {{-- Camera scanner --}}
                <div class="hw-row">
                    <span class="hw-icon"><x-icon name="camera" class="w-5 h-5" /></span>
                    <div class="hw-row-body">
                        <div class="hw-row-title">{{ __('hardware.devices.camera') }}</div>
                        <div class="hw-row-meta">{{ __('hardware.devices.camera_hint') }}</div>
                    </div>
                    <span class="hw-status hw-status-ok" x-show="cameraSupported">{{ __('hardware.devices.available') }}</span>
                    <span class="hw-status hw-status-muted" x-show="!cameraSupported" x-cloak>{{ __('hardware.devices.unavailable') }}</span>
                </div>
            </div>
        </div>

        {{-- Recent prints --}}
        <div class="card mt-5">
            <div class="card-header"><div>
                <div class="card-title">{{ __('hardware.recent.title') }}</div>
            </div></div>
            <div class="card-body">
                <div class="hw-stats">
                    <div class="hw-stat">
                        <div class="hw-stat-value">{{ $stats['total'] }}</div>
                        <div class="hw-stat-label">{{ __('hardware.recent.total') }}</div>
                    </div>
                    <div class="hw-stat">
                        <div class="hw-stat-value hw-stat-ok">{{ $stats['success'] }}</div>
                        <div class="hw-stat-label">{{ __('hardware.recent.success') }}</div>
                    </div>
                    <div class="hw-stat">
                        <div class="hw-stat-value {{ $stats['failed'] > 0 ? 'hw-stat-bad' : '' }}">{{ $stats['failed'] }}</div>
                        <div class="hw-stat-label">{{ __('hardware.recent.failed') }}</div>
                    </div>
                    <div class="hw-stat">
                        <div class="hw-stat-value">{{ $stats['last_at'] ? format_datetime($stats['last_at']) : '—' }}</div>
                        <div class="hw-stat-label">{{ __('hardware.recent.last') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
