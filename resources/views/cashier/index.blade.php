<x-cashier-layout :title="__('cashier.title')">
    @php
        // Customer-Facing Display (CFD). The channel is scoped to the bound
        // terminal so two POS on one machine can't cross-talk. The launcher
        // button only shows when the terminal has the display switched on.
        $cfdChannel   = 'pos-cfd:'.(current_terminal_id() ?: 'default');
        $cfdEnabled   = current_terminal()?->cfdEnabled() ?? false;
        $cfdTransport = current_terminal()?->cfdTransport() ?? 'same_machine';
    @endphp
    {{-- Settings-driven layout / sizing. The tile-density class stays
         server-rendered (tile size is an admin-set default, not a live
         toggle); the cart-side class binds REACTIVELY to `currentLayout`
         so the toolbar's Lane / Beam segmented control flips it on the
         fly without a reload. --}}
    <div class="cashier-page cashier-tiles-{{ $cashierSettings['tile_size'] }}"
         :class="'cashier-layout-' + currentLayout"
         x-data="cashierPage({
             completeUrl:              '{{ route('cashier.complete') }}',
             customerSearchUrl:        '{{ route('cashier.customers.search') }}',
             customerStoreUrl:         '{{ route('cashier.customers.store') }}',
             batchesUrl:               '{{ route('cashier.batches') }}',
             holdUrl:                  '{{ route('cashier.hold') }}',
             holdsListUrl:             '{{ route('cashier.holds.list') }}',
             holdResumeUrlTemplate:    '{{ route('cashier.holds.resume', ['sale' => '__ID__']) }}',
             holdVoidUrlTemplate:      '{{ route('cashier.holds.void', ['sale' => '__ID__']) }}',
             storeId:                  {{ Js::from($store->id) }},
             storeName:                {{ Js::from($store->name) }},
             products:                 {{ Js::from($products) }},
             quickPickIds:             {{ Js::from($quickPickIds ?? []) }},
             categories:               {{ Js::from($categories->map(fn ($c) => ['id' => (string) $c->id, 'name' => $c->name, 'color' => $c->color])->values()) }},
             {{-- `$paymentMethods` arrives as a pre-shaped array
                  collection from SaleController::buildCashierPayload —
                  already filtered to the fields the JS needs (including
                  the UPI `vpa` + `payee_name` for the cashier QR), so
                  we emit it as-is. --}}
             paymentMethods:           {{ Js::from($paymentMethods->values()) }},
             returnReasons:            {{ Js::from($returnReasons->values()) }},
             successDetailUrlTemplate: '{{ route('admin.sales.show', ['sale' => '__ID__']) }}',
             receiptUrlTemplate:       '{{ route('admin.sales.receipt', ['sale' => '__ID__']) }}',
             printPayloadUrlTemplate:  '{{ route('admin.sales.print-payload', ['sale' => '__ID__']) }}',
             refundPrintPayloadUrlTemplate: '{{ route('admin.sales.returns.print-payload', ['saleReturn' => '__ID__']) }}',
             correctedCopyPrintPayloadUrlTemplate: '{{ route('admin.sales.corrected-copy-print-payload', ['sale' => '__ID__']) }}',
             printLogUrl:              '{{ route('admin.print-logs.store') }}',
             reprintLastUrl:           '{{ route('cashier.reprint-last') }}',
             recentsListUrl:           '{{ route('cashier.recents') }}',
             drawerNoSaleUrl:          {{ !empty($activeShiftId) ? Js::from(route('admin.shifts.cash-drawer.record', $activeShiftId)) : 'null' }},
             refundLookupUrl:          '{{ route('cashier.refund.lookup') }}',
             refundShowUrlTemplate:    '{{ route('cashier.refund.show',  ['sale' => '__ID__']) }}',
             refundStoreUrlTemplate:   '{{ route('cashier.refund.store', ['sale' => '__ID__']) }}',
             refundStoreBlindUrl:      '{{ route('cashier.refund.store-blind') }}',
             refundApprovalUrl:        '{{ route('cashier.refund.approve') }}',
             gatewayStartUrl:          '{{ route('cashier.gateways.start') }}',
             gatewayStatusUrl:         '{{ route('cashier.gateways.status') }}',
             posSessionCreateUrl:      '{{ route('cashier.pos-sessions.create') }}',
             posSessionStatusUrlTemplate: '{{ route('cashier.pos-sessions.status', ['uuid' => '__UUID__']) }}',
             posSessionCancelUrlTemplate: '{{ route('cashier.pos-sessions.cancel', ['uuid' => '__UUID__']) }}',
             {{-- Apple Pay / Google Pay wallet buttons — both ride on this
                  one Stripe payment method (see SaleController::buildCashierPayload).
                  Null hides the wallet buttons entirely (Stripe not configured). --}}
             stripeMethodId:           {{ Js::from($stripeMethodId) }},
             settings:                 {{ Js::from($cashierSettings) }},
             {{-- Company/store receipt settings + translated labels so an
                  OFFLINE sale can compose + print its receipt fully client-
                  side (offline-sync doc §13). Null on paging responses. --}}
             receipt:                  {{ Js::from($receiptConfig ?? null) }},
             {{-- Pre-translated toast/validation/confirm text for the JS
                  factory — see SaleController::cashier() and
                  resources/js/cashier/cashier-page.js. Every call site
                  keeps its original English fallback inline, so a missing
                  key here never blanks the UI. --}}
             labels:                   {{ Js::from($labels ?? []) }},
             {{-- Customer-Facing Display (CFD) — same-machine channel +
                  the second-screen URL. See docs/features/customer-display.md. --}}
             cfdChannel:               {{ Js::from($cfdChannel) }},
             displayUrl:               '{{ route('cashier.display') }}',
             cfdTransport:             {{ Js::from($cfdTransport) }},
             cfdPushUrl:               '{{ route('cashier.display.push') }}',
         })"
         @keydown.alt.c.window.prevent="$dispatch('open-calculator')"
         @keydown.escape.window="overflowOpen = false; customerPickerOpen = false">

        {{-- Customer-Facing Display bridge. This hidden element re-runs
             publishCfd() reactively whenever the cart / totals / customer
             change (x-effect tracks whatever the snapshot reads), pushing a
             fresh frame to the second screen. No-op when no display window
             is open. See docs/features/customer-display.md. --}}
        <div x-effect="publishCfd()" hidden aria-hidden="true"></div>

        {{-- Tool strip: search | scan | overflow. Sits ABOVE the
             two-column layout so it spans the full page width — same
             real-world POS pattern where the search bar covers the
             entire chrome. --}}
        <div class="cashier-toolbar">
            <div class="cashier-search">
                <span class="cashier-search-icon"><x-icon name="search" class="w-5 h-5" /></span>
                <input type="text"
                       data-cashier-search
                       class="cashier-search-input"
                       x-model="searchQuery"
                       @keydown.enter.prevent="onSearchEnter()"
                       @keydown.escape="searchQuery = ''"
                       autocomplete="off"
                       spellcheck="false"
                       placeholder="{{ __('cashier.search.placeholder') }}">
                <button type="button"
                        class="cashier-search-clear"
                        x-show="searchQuery.length > 0" x-cloak
                        @click="searchQuery = ''; focusSearch()"
                        aria-label="{{ __('cashier.search.clear') }}">
                    <x-icon name="x" class="w-4 h-4" />
                </button>
                <span class="cashier-search-shortcut">/</span>
            </div>

            <div class="cashier-scan">
                <span class="cashier-search-icon"><x-icon name="barcode" class="w-5 h-5" /></span>
                <input type="text"
                       data-cashier-scan
                       class="cashier-search-input cashier-scan-input mono"
                       x-model="scanQuery"
                       @keydown.enter.prevent="onScanEnter()"
                       @keydown.escape="scanQuery = ''"
                       autocomplete="off"
                       spellcheck="false"
                       placeholder="{{ __('cashier.scan.placeholder') }}">
                <span class="cashier-search-shortcut">{{ __('cashier.scan.shortcut') }}</span>
            </div>

            {{-- Camera scanner — for tablets / devices without a USB
                 scanner. Hidden when the device has no camera. --}}
            <button type="button"
                    x-show="cameraSupported"
                    class="cashier-seg-btn cashier-camera-btn"
                    @click="openCamera()"
                    :title="@js(__('cashier.scan.camera'))"
                    aria-label="{{ __('cashier.scan.camera') }}">
                <x-icon name="camera" class="w-5 h-5" />
            </button>

            {{-- Live layout switcher — Beam / Lane / Counter. The
                 cashier picks the shape; choice is stored in localStorage
                 so the same terminal stays consistent for the same
                 cashier. Mirrors the segmented control in the
                 design-system/Checkout.html mockup. --}}
            <div class="cashier-seg" role="group" aria-label="{{ __('cashier.layout.aria') }}">
                <button type="button"
                        :class="{ 'is-active': currentLayout === 'beam' }"
                        @click="setLayout('beam')"
                        :aria-pressed="currentLayout === 'beam'"
                        :title="@js(__('cashier.layout.beam'))">
                    <x-icon name="panel-left-open" class="w-4 h-4" />
                    <span class="cashier-seg-label">{{ __('cashier.layout.beam_short') }}</span>
                </button>
                <button type="button"
                        :class="{ 'is-active': currentLayout === 'lane' }"
                        @click="setLayout('lane')"
                        :aria-pressed="currentLayout === 'lane'"
                        :title="@js(__('cashier.layout.lane'))">
                    <x-icon name="panel-left-close" class="w-4 h-4" />
                    <span class="cashier-seg-label">{{ __('cashier.layout.lane_short') }}</span>
                </button>
                <button type="button"
                        :class="{ 'is-active': currentLayout === 'counter' }"
                        @click="setLayout('counter')"
                        :aria-pressed="currentLayout === 'counter'"
                        :title="@js(__('cashier.layout.counter'))">
                    <x-icon name="list" class="w-4 h-4" />
                    <span class="cashier-seg-label">{{ __('cashier.layout.counter_short') }}</span>
                </button>
                <button type="button"
                        :class="{ 'is-active': currentLayout === 'focus' }"
                        @click="setLayout('focus')"
                        :aria-pressed="currentLayout === 'focus'"
                        :title="@js(__('cashier.layout.focus'))">
                    <x-icon name="maximize" class="w-4 h-4" />
                    <span class="cashier-seg-label">{{ __('cashier.layout.focus_short') }}</span>
                </button>
            </div>

            {{-- Fullscreen toggle — maximises the POS for a distraction-free
                 till. Reflects Esc/F11 exits via the Fullscreen API. --}}
            <button type="button"
                    class="cashier-camera-btn"
                    x-data="fullscreenToggle()"
                    @click="toggle()"
                    :title="isFull ? @js(__('cashier.fullscreen.exit')) : @js(__('cashier.fullscreen.enter'))"
                    :aria-label="isFull ? @js(__('cashier.fullscreen.exit')) : @js(__('cashier.fullscreen.enter'))">
                <template x-if="!isFull"><x-icon name="maximize" class="w-5 h-5" /></template>
                <template x-if="isFull"><x-icon name="minimize" class="w-5 h-5" /></template>
            </button>

            {{-- Bound-terminal chip (Slice B) — read-only indicator of
                 which till this device is on. Selection happens via the
                 shift gate or Admin → Terminals. --}}
            @if (!empty($shiftGate['currentTerminalName']))
            <div class="cashier-terminal-chip" title="{{ __('shifts.gate.terminal_label') }}">
                <x-icon name="pos" class="w-4 h-4" />
                <span>{{ $shiftGate['currentTerminalName'] }}</span>
            </div>
            @endif

            {{-- Connectivity indicator (Slice 1) — small dot + popover.
                 Green/yellow/red reflects the connectivity state machine;
                 the popover surfaces last_synced + a "refresh now" CTA. --}}
            <div class="cashier-conn" @click.outside="connOpen = false" x-data="{ connOpen: false }">
                <button type="button"
                        class="cashier-conn-btn"
                        :class="{
                            'is-online':   connectivity.status === 'online',
                            'is-degraded': connectivity.status === 'degraded',
                            'is-offline':  connectivity.status === 'offline',
                            'is-unknown':  connectivity.status === 'unknown',
                        }"
                        @click="connOpen = !connOpen"
                        :title="@js(__('cashier.conn.title')) + ': ' + connectivity.status">
                    <span class="cashier-conn-dot"
                          :class="{ 'is-spinning': refreshing }"></span>
                    <span class="cashier-conn-label" x-text="connectivity.status === 'unknown' ? '—' : connectivity.status"></span>
                    <span class="cashier-conn-queue-badge"
                          x-show="queueDepth > 0" x-cloak
                          x-text="queueDepth"></span>
                </button>
                <div class="cashier-conn-popover"
                     x-show="connOpen" x-cloak
                     x-transition.opacity.duration.120ms>
                    <div class="cashier-conn-row">
                        <span class="cashier-conn-row-label">{{ __('cashier.conn.status') }}</span>
                        <span class="cashier-conn-row-value" x-text="connectivity.status"></span>
                    </div>
                    <div class="cashier-conn-row">
                        <span class="cashier-conn-row-label">{{ __('cashier.conn.last_synced') }}</span>
                        <span class="cashier-conn-row-value" x-text="lastSyncedLabel"></span>
                    </div>
                    <div class="cashier-conn-row">
                        <span class="cashier-conn-row-label">{{ __('cashier.conn.queue') }}</span>
                        <span class="cashier-conn-row-value tnum"
                              :class="queueDepth > 0 ? 'cashier-conn-queue-pending' : ''"
                              x-text="queueDepth === 0 ? @js(__('cashier.conn.queue_empty')) : queueDepth + ' ' + @js(__('cashier.conn.queue_pending'))"></span>
                    </div>
                    <button type="button"
                            class="cashier-conn-refresh"
                            :disabled="refreshing"
                            @click="refreshNow(); connOpen = false">
                        <x-icon name="refresh" class="w-4 h-4" />
                        <span x-text="refreshing ? @js(__('cashier.conn.refreshing')) : @js(__('cashier.conn.refresh'))"></span>
                    </button>

                    {{-- Slice 3: Install prompt — only renders when the
                         browser has fired `beforeinstallprompt`. --}}
                    <button type="button"
                            class="cashier-conn-refresh cashier-conn-install"
                            x-show="pwa.installable" x-cloak
                            @click="installNow(); connOpen = false">
                        <x-icon name="download" class="w-4 h-4" />
                        <span>{{ __('cashier.conn.install') }}</span>
                    </button>

                    {{-- Slice 3: Update prompt — only renders when a new
                         service worker is waiting. --}}
                    <button type="button"
                            class="cashier-conn-refresh cashier-conn-update"
                            x-show="pwa.updateAvailable" x-cloak
                            @click="updateNow(); connOpen = false">
                        <x-icon name="refresh" class="w-4 h-4" />
                        <span>{{ __('cashier.conn.update') }}</span>
                    </button>
                </div>
            </div>

            <div class="cashier-overflow" @click.outside="overflowOpen = false">
                <button type="button"
                        class="cashier-icon-btn me-2"
                        @click="$dispatch('open-calculator')"
                        aria-label="{{ __('Calculator') }}"
                        title="{{ __('Calculator') }} (Alt+C)">
                    <x-icon name="calculator" class="w-[18px] h-[18px]" />
                </button>

                <button type="button"
                        class="cashier-icon-btn"
                        :class="{ 'is-active': overflowOpen }"
                        @click="toggleOverflow()"
                        aria-label="{{ __('cashier.overflow.open') }}"
                        title="{{ __('cashier.overflow.open') }}">
                    <x-icon name="dots" class="w-[18px] h-[18px]" />
                </button>
                <div class="cashier-overflow-menu"
                     x-show="overflowOpen" x-cloak
                     x-transition.opacity.duration.120ms>
                    <button type="button"
                            class="cashier-overflow-item"
                            :disabled="cart.length === 0"
                            @click="clearCart(); closeOverflow();">
                        <x-icon name="trash" class="w-4 h-4" />
                        <span>{{ __('cashier.overflow.clear_cart') }}</span>
                    </button>
                    <div class="cashier-overflow-sep" role="separator"></div>
                    <a href="{{ route('cashier.reprint-last') }}"
                       target="_blank"
                       class="cashier-overflow-item"
                       @click="closeOverflow()">
                        <x-icon name="receipt" class="w-4 h-4" />
                        <span>{{ __('cashier.overflow.reprint_last') }}</span>
                    </a>
                    {{-- Open the Customer-Facing Display on the second
                         screen. Only shown when this terminal has the
                         display switched on. See customer-display.md. --}}
                    @if ($cfdEnabled)
                    <button type="button"
                            class="cashier-overflow-item"
                            @click="openCustomerDisplay(); closeOverflow();">
                        <x-icon name="pos" class="w-4 h-4" />
                        <span>{{ __('cashier.overflow.customer_display') }}</span>
                    </button>
                    @endif
                    {{-- Close shift (Slice B) — shortcut to the close form
                         when this cashier has an open shift. Cash count +
                         Z-report live on the admin close screen for now. --}}
                    @if (!empty($activeShiftId))
                    <a href="{{ route('admin.shifts.x-report', $activeShiftId) }}"
                       target="_blank"
                       class="cashier-overflow-item"
                       @click="closeOverflow()">
                        <x-icon name="receipt" class="w-4 h-4" />
                        <span>{{ __('shifts.actions.view_x_report') }}</span>
                    </a>
                    <a href="{{ route('admin.shifts.close.form', $activeShiftId) }}"
                       class="cashier-overflow-item"
                       @click="closeOverflow()">
                        <x-icon name="lock" class="w-4 h-4" />
                        <span>{{ __('shifts.actions.close_shift') }}</span>
                    </a>
                    @endif
                    {{-- Close Day (Phase 4) — manager-only, separate from an
                         employee closing their own shift above. Only shown
                         once a trading day is actually open today. --}}
                    @if (($canCloseDay ?? false) && !empty($closeDayTradingDayId))
                    <a href="{{ route('admin.shifts.day.close.form', $closeDayTradingDayId) }}"
                       class="cashier-overflow-item"
                       @click="closeOverflow()">
                        <x-icon name="lock" class="w-4 h-4" />
                        <span>{{ __('shifts.day.close.title') }}</span>
                    </a>
                    @endif
                    {{-- Browsing + building up a refund only needs sales.create — a
                         cashier without sales.refund can still open this, they'll
                         just get the manager-PIN prompt before it actually submits. --}}
                    @if (auth()->user()?->hasPermission('sales.create'))
                    <button type="button"
                            class="cashier-overflow-item"
                            @click="openRefundLookup(); closeOverflow();">
                        <x-icon name="back" class="w-4 h-4" />
                        <span>{{ __('cashier.overflow.refund') }}</span>
                    </button>
                    <button type="button"
                            class="cashier-overflow-item"
                            @click="openBlindRefund(); closeOverflow();">
                        <x-icon name="camera" class="w-4 h-4" />
                        <span>{{ __('cashier.overflow.refund_by_scan') }}</span>
                    </button>
                    @endif
                    {{-- Open drawer without a sale — same permission/audit
                         trail as the admin shift page's version (see
                         resources/js/admin/drawer-opener.js), but doesn't
                         reload the screen — the cashier is mid-shift here,
                         not on a standalone admin page. --}}
                    @if (!empty($activeShiftId) && ($canOpenDrawerNoSale ?? false))
                    <button type="button"
                            class="cashier-overflow-item"
                            @click="openDrawerNoSale(); closeOverflow();">
                        <x-icon name="cash" class="w-4 h-4" />
                        <span>{{ __('cashier.overflow.drawer_no_sale') }}</span>
                    </button>
                    @endif
                    <button type="button"
                            class="cashier-overflow-item"
                            @click="openHeldDrawer(); closeOverflow();">
                        <x-icon name="archive" class="w-4 h-4" />
                        <span>{{ __('cashier.held.overflow_open') }}</span>
                    </button>
                    <button type="button" class="cashier-overflow-item"
                            @click="openRecentsDrawer(); closeOverflow();">
                        <x-icon name="clock" class="w-4 h-4" />
                        <span>{{ __('cashier.overflow.recents') }}</span>
                    </button>
                    <button type="button" class="cashier-overflow-item"
                            @click="openShortcutsHelp(); closeOverflow();">
                        <x-icon name="key" class="w-4 h-4" />
                        <span>{{ __('cashier.overflow.shortcut_help') }}</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Category chips — also full-width, ABOVE the split. The
             Counter layout uses a vertical sidebar inside the split
             instead; this row is hidden then via CSS on the page. --}}
        <div class="cashier-cats">
            <button type="button"
                    class="cashier-cat"
                    :class="{ 'is-active': selectedCategory === 'all' }"
                    @click="pickCategory('all')">
                {{ __('cashier.categories.all') }}
                <span class="cashier-cat-count" x-text="products.length"></span>
            </button>
            <template x-for="cat in categories" :key="cat.id">
                <button type="button"
                        class="cashier-cat"
                        :class="{ 'is-active': selectedCategory === cat.id }"
                        :style="cat.color ? `--cat-color: ${cat.color}` : ''"
                        @click="pickCategory(cat.id)">
                    <span class="cashier-cat-dot" x-show="cat.color"></span>
                    <span x-text="cat.name"></span>
                    <span class="cashier-cat-count" x-text="products.filter(p => String(p.category_id) === cat.id).length"></span>
                </button>
            </template>
        </div>

        {{-- Two/three-column split:
             - Beam:    [catalog]  [cart]
             - Lane:    [cart]     [catalog]   (reordered via CSS `order:`)
             - Counter: [sidebar]  [list]      [cart]
             - Focus:   [action rail] [cart, full-height, nothing above it] [catalog, collapsible] --}}
        <div class="cashier-layout">
            {{-- ── Focus action rail — Discount / Customer / Check price /
                 Held sales, left-of-cart. Only rendered visibly in Focus
                 mode (CSS hides it otherwise); every button re-homes an
                 action that already exists elsewhere in the toolbar/
                 overflow menu — same handlers, just closer at hand for a
                 layout built around one big scanned-item list. --}}
            <aside class="cashier-action-rail">
                <button type="button" class="cashier-rail-btn" @click="showDiscount = true">
                    <x-icon name="tag" class="w-5 h-5" />
                    <span>{{ __('cashier.action_rail.discount') }}</span>
                </button>
                <button type="button" class="cashier-rail-btn" @click="openCustomerPicker()">
                    <x-icon name="customers" class="w-5 h-5" />
                    <span>{{ __('cashier.action_rail.customer') }}</span>
                </button>
                <button type="button" class="cashier-rail-btn" @click="openPriceCheck()">
                    <x-icon name="search" class="w-5 h-5" />
                    <span>{{ __('cashier.action_rail.check_price') }}</span>
                </button>
                <button type="button" class="cashier-rail-btn" @click="openHeldDrawer()">
                    <x-icon name="archive" class="w-5 h-5" />
                    <span>{{ __('cashier.action_rail.held') }}</span>
                </button>
            </aside>

            {{-- ── Counter sidebar (vertical category nav) ─────────
                 Hidden via CSS in Lane/Beam; visible only in Counter.
                 Mirrors the design-system mockup's Layout C left rail. --}}
            <aside class="cashier-side">
                <div class="cashier-side-head">{{ __('cashier.listrow.category_label') }}</div>
                <nav class="cashier-side-list">
                    <button type="button"
                            class="cashier-side-item"
                            :class="{ 'is-active': selectedCategory === 'all' }"
                            @click="pickCategory('all')">
                        <span x-text="@js(__('cashier.categories.all'))"></span>
                        <span class="cashier-side-count tnum" x-text="products.length"></span>
                    </button>
                    <template x-for="cat in categories" :key="cat.id">
                        <button type="button"
                                class="cashier-side-item"
                                :class="{ 'is-active': selectedCategory === cat.id }"
                                @click="pickCategory(cat.id)">
                            <span class="cashier-side-dot" x-show="cat.color" :style="cat.color ? `background:${cat.color}` : ''"></span>
                            <span class="cashier-side-name" x-text="cat.name"></span>
                            <span class="cashier-side-count tnum" x-text="products.filter(p => String(p.category_id) === cat.id).length"></span>
                        </button>
                    </template>
                </nav>
            </aside>

            {{-- ── Catalog: tile grid + dense list (mode-swapped) ───
                 Front-and-centre in every layout, including Focus — the
                 collapsible panel in Focus is the CART (below), not this. --}}
            <section class="cashier-catalog" :class="{ 'mobile-tab-hidden': mobileTab !== 'catalog' }">
                {{-- Quick picks — most-sold products as tappable chips. Toggled
                     by Settings → Cashier "Show Quick picks row". Each chip
                     reuses the normal addToCart() path. --}}
                <div class="cashier-quickpicks" x-show="quickPicks.length" x-cloak>
                    <span class="cashier-quickpicks-label">{{ __('Quick picks') }}</span>
                    <div class="cashier-quickpicks-row">
                        <template x-for="p in quickPicks" :key="'qp-' + p.id">
                            <button type="button"
                                    class="cashier-quickpick"
                                    :class="{ 'is-out': isOutOfStock(p) || isStockNotListed(p) }"
                                    :disabled="(isOutOfStock(p) || isStockNotListed(p)) && !oversellAllowedFor(p)"
                                    @click="addToCart(p)">
                                <span class="cashier-quickpick-name" x-text="p.name"></span>
                                <span class="cashier-quickpick-price tnum" x-text="money(p.selling_price)"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Lane/Beam view: tile grid. --}}
                <div class="cashier-grid">
                    <template x-for="p in visibleProducts" :key="p.id">
                        {{-- Tile click always adds to cart — even "out of
                             stock" tiles are clickable. The server-side
                             CompleteSale action enforces the negative-stock
                             policy and surfaces a clear toast if the sale
                             actually fails. The badge is informational. --}}
                        <button type="button"
                                class="cashier-tile"
                                :class="{ 'is-out': isOutOfStock(p), 'is-unavailable': isStockNotListed(p) }"
                                :disabled="(isOutOfStock(p) || isStockNotListed(p)) && !oversellAllowedFor(p)"
                                :title="isStockNotListed(p) ? @js(__('cashier.stock.not_listed'))
                                        : isOutOfStock(p) ? @js(__('cashier.stock.not_available'))
                                        : ''"
                                @click="addToCart(p)">
                            <span class="cashier-tile-art">
                                <template x-if="p.image_url">
                                    <img :src="p.image_url" alt="" loading="lazy">
                                </template>
                                <template x-if="!p.image_url">
                                    <span class="cashier-tile-mark" x-text="(p.name || '?').slice(0,1).toUpperCase()"></span>
                                </template>
                                {{-- Variant count chip — mirrors mockup
                                     "opts" pattern at Checkout.html L384.
                                     More useful than a generic "Sizes"
                                     label because the cashier knows
                                     up-front how many to scan through. --}}
                                <template x-if="p.has_variants">
                                    <span class="cashier-tile-variants">
                                        <span x-text="p.variant_count"></span>
                                        <span>{{ __('cashier.tile.options_suffix') }}</span>
                                    </span>
                                </template>
                                {{-- KIT badge — shown in place of (or
                                     alongside) the variants chip for
                                     `type = 'kit'` products. The
                                     components are listed below the
                                     name so the cashier knows what's
                                     in the bundle at a glance. --}}
                                <template x-if="p.is_kit">
                                    <span class="cashier-tile-kit">{{ __('cashier.tile.kit') }}</span>
                                </template>
                                <template x-if="stockTone(p)">
                                    <span class="cashier-tile-stock"
                                          :class="`is-${stockTone(p)}`"
                                          x-text="stockTone(p) === 'out' ? @js(__('cashier.stock.out')) : (@js(__('cashier.stock.low_label')) + ' ' + _fmtQty(p.on_hand))"></span>
                                </template>
                            </span>
                            <span class="cashier-tile-name" x-text="p.name"></span>
                            {{-- Stock detail line under the name — matches
                                 the reference's "X unit in stock" sub-line.
                                 Hidden when track_stock=false or when on_hand
                                 is null (no row yet). --}}
                            <template x-if="tileStockLabel(p)">
                                <span class="cashier-tile-stockdetail" x-text="tileStockLabel(p)"></span>
                            </template>
                            {{-- Kit components — one-line summary
                                 ("Includes: 2× Cola, 1× Chips") with
                                 ellipsis. Full breakdown lives on the
                                 cart line once the kit is added. --}}
                            <template x-if="p.is_kit && p.kit_items?.length">
                                <span class="cashier-tile-kit-list"
                                      :title="kitSummary(p, false)"
                                      x-text="kitSummary(p, false)"></span>
                            </template>
                            <span class="cashier-tile-price-row">
                                {{-- "from" prefix only when the price is
                                     genuinely a range (variant min < max).
                                     Otherwise the helper returns a single
                                     number and we omit the prefix. --}}
                                <template x-if="settings.show_from_prefix && p.has_variants && p.price_min !== p.price_max">
                                    <span class="cashier-tile-from">{{ __('cashier.tile.from_prefix') }}</span>
                                </template>
                                <span class="cashier-tile-price num tnum" x-text="tilePriceLabel(p)"></span>
                                {{-- MRP strike-through — only shown when
                                     it's strictly higher than the price
                                     (or, for variants, the min price). --}}
                                <template x-if="tileMrpLabel(p)">
                                    <span class="cashier-tile-mrp num tnum" x-text="tileMrpLabel(p)"></span>
                                </template>
                                {{-- Regular-price strike-through — only when
                                     an active sale_price makes tilePriceLabel
                                     the discounted number, not the regular one. --}}
                                <template x-if="tileWasLabel(p)">
                                    <span class="cashier-tile-mrp num tnum" x-text="tileWasLabel(p)"></span>
                                </template>
                            </span>
                        </button>
                    </template>

                    {{-- Empty state when filters return nothing. --}}
                    <div class="cashier-grid-empty" x-show="visibleProducts.length === 0" x-cloak>
                        <x-icon name="search" class="w-10 h-10 fg-tertiary" />
                        <div class="cashier-grid-empty-title">{{ __('cashier.grid.empty_title') }}</div>
                        <div class="cashier-grid-empty-sub">{{ __('cashier.grid.empty_sub') }}</div>
                    </div>

                    {{-- Load more — reveals the next chunk of filtered
                         products. Spans the full grid row so it sits at
                         the bottom of the grid as a wide CTA. --}}
                    <div class="cashier-grid-loadmore" x-show="hasMoreProducts" x-cloak>
                        {{-- Infinite-scroll sentinel: only has layout while
                             "hasMoreProducts" is true, so it intersects (and
                             auto-loads the next chunk) exactly when there's
                             more to show. --}}
                        <span class="js-grid-sentinel" aria-hidden="true"></span>
                        <button type="button"
                                class="pos-btn pos-btn-ghost"
                                @click="loadMoreProducts()">
                            <x-icon name="plus" class="w-4 h-4" />
                            {{ __('cashier.grid.load_more') }}
                            <span class="cashier-grid-loadmore-count" x-text="'(' + hiddenCount + ')'"></span>
                        </button>
                    </div>
                </div>

                {{-- Counter view: dense list rows. Matches the
                     `design-system/Checkout.html` Layout C "list-row"
                     pattern — thumb | PLU/SKU | name+meta | price | add.
                     Hidden via CSS in Lane/Beam modes. --}}
                <div class="cashier-listrows">
                    {{-- Sub-header — just the category breadcrumb +
                         count. The mockup also showed "Sort: Name /
                         Show: All" stubs, but they don't do anything
                         in our build yet, so we omit them rather than
                         confuse the cashier with non-functional UI. --}}
                    <div class="cashier-listrows-sub">
                        <div class="cashier-listrows-sub-left">
                            <span class="cashier-listrows-sub-cat"
                                  x-text="currentCategoryName ?? @js(__('cashier.categories.all'))"></span>
                            <span class="cashier-listrows-sub-sep">·</span>
                            <span class="tnum" x-text="filteredProducts.length"></span>
                            <span class="cashier-listrows-sub-meta">{{ __('cashier.listrow.items_suffix') }}</span>
                        </div>
                    </div>

                    <div class="cashier-listrows-head">
                        <span class="cashier-listrow-thumb"></span>
                        <span class="cashier-listrow-sku">{{ __('cashier.listrow.plu_header') }}</span>
                        <span class="cashier-listrow-name">{{ __('cashier.listrow.item_header') }}</span>
                        <span class="cashier-listrow-price">{{ __('cashier.listrow.price_header') }}</span>
                        <span class="cashier-listrow-add"></span>
                    </div>

                    <template x-for="p in visibleProducts" :key="'row-' + p.id">
                        <button type="button"
                                class="cashier-listrow"
                                :class="{ 'is-out': isOutOfStock(p), 'is-unavailable': isStockNotListed(p) }"
                                :disabled="(isOutOfStock(p) || isStockNotListed(p)) && !oversellAllowedFor(p)"
                                :title="isStockNotListed(p) ? @js(__('cashier.stock.not_listed'))
                                        : isOutOfStock(p) ? @js(__('cashier.stock.not_available'))
                                        : ''"
                                @click="addToCart(p)">
                            <span class="cashier-listrow-thumb">
                                <template x-if="p.image_url">
                                    <img :src="p.image_url" alt="" loading="lazy">
                                </template>
                                <template x-if="!p.image_url">
                                    <span class="cashier-listrow-mark" x-text="(p.name || '?').slice(0,1).toUpperCase()"></span>
                                </template>
                            </span>
                            <span class="cashier-listrow-sku mono" x-text="p.sku || '—'"></span>
                            <span class="cashier-listrow-name">
                                <span class="cashier-listrow-title">
                                    <span x-text="p.name"></span>
                                    <template x-if="p.has_variants">
                                        <span class="cashier-listrow-tag">
                                            <span x-text="p.variant_count"></span>
                                            <span>{{ __('cashier.tile.options_suffix') }}</span>
                                        </span>
                                    </template>
                                    <template x-if="p.is_kit">
                                        <span class="cashier-listrow-tag cashier-listrow-tag-kit">{{ __('cashier.tile.kit') }}</span>
                                    </template>
                                    <template x-if="stockTone(p)">
                                        <span class="cashier-listrow-stock"
                                              :class="`is-${stockTone(p)}`"
                                              x-text="stockTone(p) === 'out' ? @js(__('cashier.stock.out')) : (@js(__('cashier.stock.low_label')) + ' ' + _fmtQty(p.on_hand))"></span>
                                    </template>
                                </span>
                                <span class="cashier-listrow-meta"
                                      x-text="p.is_kit ? kitSummary(p) : ((p.unit ? p.unit : 'each') + (tileStockLabel(p) ? ' · ' + tileStockLabel(p) : ''))"></span>
                            </span>
                            <span class="cashier-listrow-price">
                                <span class="cashier-listrow-price-v num tnum" x-text="tilePriceLabel(p)"></span>
                                <template x-if="tileMrpLabel(p)">
                                    <span class="cashier-listrow-price-mrp num tnum" x-text="tileMrpLabel(p)"></span>
                                </template>
                                <template x-if="tileWasLabel(p)">
                                    <span class="cashier-listrow-price-mrp num tnum" x-text="tileWasLabel(p)"></span>
                                </template>
                            </span>
                            <span class="cashier-listrow-add" :aria-label="@js(__('cashier.listrow.add_aria', ['name' => '__NAME__'])).replace('__NAME__', p.name)">
                                <x-icon name="plus" class="w-4 h-4" />
                            </span>
                        </button>
                    </template>

                    {{-- Same Load-more in list view. --}}
                    <div class="cashier-grid-loadmore" x-show="hasMoreProducts" x-cloak>
                        {{-- Infinite-scroll sentinel: only has layout while
                             "hasMoreProducts" is true, so it intersects (and
                             auto-loads the next chunk) exactly when there's
                             more to show. --}}
                        <span class="js-grid-sentinel" aria-hidden="true"></span>
                        <button type="button"
                                class="pos-btn pos-btn-ghost"
                                @click="loadMoreProducts()">
                            <x-icon name="plus" class="w-4 h-4" />
                            {{ __('cashier.grid.load_more') }}
                            <span class="cashier-grid-loadmore-count" x-text="'(' + hiddenCount + ')'"></span>
                        </button>
                    </div>
                </div>
            </section>

            {{-- ── RIGHT: cart ───────────────────────────────────────── --}}
            <aside class="cashier-cart" :class="{ 'mobile-tab-hidden': mobileTab !== 'cart' }">
                {{-- Order header — mirrors the canonical mockup's cart top
                     row at design-system/Checkout.html L191–199. Eyebrow
                     above a draft order id; "New" clears the cart so the
                     cashier can start a fresh order without leaving the
                     screen. --}}
                {{-- Order header — eyebrow only, no cosmetic UUID
                     number. The real number (SALE-MAIN-202606-XXXX) is
                     assigned server-side at checkout / hold; showing a
                     made-up one here was confusing cashiers. The receipt
                     and success overlay show the real number once it's
                     issued. --}}
                <div class="cashier-cart-order">
                    <div class="cashier-cart-order-meta">
                        <span class="cashier-cart-order-eyebrow">{{ __('cashier.cart.order_eyebrow') }}</span>
                        <span class="cashier-cart-order-draft">{{ __('cashier.cart.draft') }}</span>
                    </div>
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost cashier-cart-new"
                            :disabled="cart.length === 0 && !customer && !discount.type"
                            @click="onClearClick()">
                        <x-icon name="trash" class="w-3.5 h-3.5" />
                        {{ __('cashier.cart.clear') }}
                    </button>
                </div>

                {{-- Customer card — bigger 2-line pill matching the
                     mockup's "Add customer / Earn loyalty…" block at
                     Checkout.html L203-215. Whole card is a button;
                     the small ✕ on the right resets to walk-in once a
                     customer is assigned. --}}
                <div class="cashier-cart-customer">
                    <button type="button"
                            class="cashier-customer-card"
                            :class="{ 'is-assigned': customer }"
                            @click="openCustomerPicker()"
                            :aria-label="customer ? @js(__('cashier.customer.change')) : @js(__('cashier.customer.add'))">
                        <span class="cashier-customer-avatar">
                            <x-icon name="user" class="w-4 h-4" />
                        </span>
                        <span class="cashier-customer-text">
                            <span class="cashier-customer-name" x-text="customer ? customer.name : @js(__('cashier.customer.add'))"></span>
                            <span class="cashier-customer-sub" x-text="customer ? ($formatPhone(customer.phone) || @js(__('cashier.customer.attached'))) : @js(__('cashier.customer.sub_default'))"></span>
                        </span>
                        <span class="cashier-kbd cashier-customer-kbd">F2</span>
                    </button>
                    <button type="button"
                            class="cashier-customer-reset"
                            x-show="customer" x-cloak
                            @click.stop="clearCustomer()"
                            :aria-label="@js(__('cashier.customer.reset'))"
                            :title="@js(__('cashier.customer.reset'))">
                        <x-icon name="x" class="w-3.5 h-3.5" />
                    </button>
                </div>

                <div class="cashier-cart-lines">
                    <template x-for="line in cart" :key="line.id">
                        {{-- Cart line — mirrors mockup Checkout.html
                             L229-273. Thumb + name+sku on top, "$X each"
                             below; right column stacks line total, qty
                             stepper, and the note/trash row. --}}
                        <div class="cashier-line" :class="{ 'is-over-stock': isLineOverStock(line) }">
                            <span class="cashier-line-thumb">
                                <template x-if="line.image_url">
                                    <img :src="line.image_url" alt="" loading="lazy">
                                </template>
                                <template x-if="!line.image_url">
                                    <span class="cashier-line-mark" x-text="(line.name || '?').slice(0,1).toUpperCase()"></span>
                                </template>
                            </span>
                            <div class="cashier-line-meta">
                                <div class="cashier-line-head">
                                    <span class="cashier-line-name" x-text="line.name"></span>
                                    <span class="cashier-line-sku mono" x-text="line.sku"></span>
                                </div>
                                <template x-if="line.variant_label">
                                    <div class="cashier-line-variant" x-text="line.variant_label"></div>
                                </template>
                                {{-- Batch label — visible only on batch-tracked
                                     lines so the cashier (and the customer
                                     peeking at the screen) sees which batch
                                     is leaving stock. Expiry is shown when
                                     known so an expiring-soon batch is
                                     obviously the right one to push. --}}
                                <template x-if="line.batch_id">
                                    <div class="cashier-line-batch mono">
                                        <span x-text="@js(__('cashier.batch.batch_prefix')) + ' ' + line.batch_number"></span>
                                        <template x-if="line.batch_expiry">
                                            <span class="fg-tertiary">
                                                · {{ __('cashier.batch.exp_prefix') }}
                                                <span x-text="line.batch_expiry"></span>
                                            </span>
                                        </template>
                                    </div>
                                </template>
                                {{-- Kit components — listed on the line
                                     so the cashier (and the customer
                                     peeking over the counter) sees what
                                     the bundle contains without opening
                                     the receipt. --}}
                                <template x-if="line.kit_items?.length">
                                    <ul class="cashier-line-kit">
                                        <template x-for="(k, i) in line.kit_items" :key="i">
                                            <li class="cashier-line-kit-item">
                                                <span class="tnum" x-text="_fmtQty(k.quantity)"></span>
                                                <span class="fg-tertiary">×</span>
                                                <span x-text="k.name"></span>
                                                <template x-if="k.variant_label">
                                                    <span class="cashier-line-kit-variant" x-text="'(' + k.variant_label + ')'"></span>
                                                </template>
                                            </li>
                                        </template>
                                    </ul>
                                </template>
                                <template x-if="line.note">
                                    <div class="cashier-line-note">
                                        <x-icon name="note" class="w-3 h-3 fg-tertiary" />
                                        <span x-text="line.note"></span>
                                    </div>
                                </template>
                                <div class="cashier-line-each tnum"
                                     x-text="line.weighed
                                         ? money(line.unit_price) + ' / ' + (line.unit || '')
                                         : money(line.unit_price) + ' ' + @js(__('cashier.line.each'))"></div>
                                {{-- Per-line discount indicator (Slice 4). --}}
                                <div class="cashier-line-disc tnum" x-show="line.discount && line.discount.type" x-cloak>
                                    <x-icon name="tag" class="w-3 h-3" />
                                    <span x-text="'−' + money(lineOwnDiscount(line)) + (line.discount?.type === 'pct' ? ' (' + line.discount.value + '%)' : '')"></span>
                                </div>
                                {{-- Per-line tax — only when the line is
                                     taxable AND there's an actual rate. The
                                     "(incl.)" suffix flips on for inclusive
                                     lines so the cashier knows the tax is
                                     baked into the unit price rather than
                                     stacked on top of it. --}}
                                <div class="cashier-line-tax tnum"
                                     x-show="_lineTax(line) > 0" x-cloak>
                                    <span>{{ __('cashier.line.tax') }}</span>
                                    <span class="num" x-text="money(_lineTax(line))"></span>
                                    <span class="cashier-line-tax-incl"
                                          x-show="line.tax_inclusive" x-cloak>{{ __('cashier.totals.tax_incl') }}</span>
                                </div>
                                {{-- Over-stock warning — visible when qty
                                     exceeds the snapshot on_hand. Server
                                     would also reject, but surfacing it
                                     inline saves a round-trip and lets
                                     the cashier clamp before payment. --}}
                                <div class="cashier-line-error"
                                     x-show="isLineOverStock(line)" x-cloak
                                     x-text="@js(__('cashier.line.over_stock', ['available' => '__N__'])).replace('__N__', _fmtQty(line.on_hand ?? 0))"></div>
                            </div>
                            <div class="cashier-line-right">
                                <div class="cashier-line-total num tnum" x-text="money(lineTotalDisplay(line))"></div>
                                <div class="cashier-line-qty">
                                    <button type="button" class="cashier-step" @click="decrement(line)" aria-label="−">
                                        <x-icon name="minus" class="w-3 h-3" />
                                    </button>
                                    {{-- Editable qty — `x-model.number.lazy`
                                         only commits on blur/Enter so the
                                         cashier can type a multi-digit number
                                         without each keystroke recomputing
                                         totals. `setQty(line, value)` guards
                                         against 0/negative input. --}}
                                    <input type="number"
                                           class="cashier-qty-value cashier-qty-input num tnum"
                                           :value="line.quantity"
                                           @change="setQty(line, $event.target.value); $event.target.value = line.quantity;"
                                           @keydown.enter.prevent="$event.target.blur()"
                                           step="any"
                                           min="0"
                                           aria-label="Quantity">
                                    <button type="button" class="cashier-step" @click="increment(line)" aria-label="+">
                                        <x-icon name="plus" class="w-3 h-3" />
                                    </button>
                                    <span class="cashier-qty-unit fg-tertiary"
                                          x-show="line.weighed" x-cloak
                                          x-text="line.unit"></span>
                                </div>
                                <div class="cashier-line-icons">
                                    <button type="button"
                                            class="cashier-line-icon"
                                            x-show="settings.can_discount" x-cloak
                                            :class="{ 'is-set': line.discount && line.discount.type }"
                                            @click="openLineDiscount(line)"
                                            :title="@js(__('cashier.line.discount'))"
                                            :aria-label="@js(__('cashier.line.discount'))">
                                        <x-icon name="tag" class="w-3.5 h-3.5" />
                                    </button>
                                    <button type="button"
                                            class="cashier-line-icon"
                                            :class="{ 'is-set': line.note }"
                                            @click="openNote(line)"
                                            :title="line.note ? @js(__('cashier.line.edit_note')) : @js(__('cashier.line.add_note'))"
                                            :aria-label="line.note ? @js(__('cashier.line.edit_note')) : @js(__('cashier.line.add_note'))">
                                        <x-icon name="note" class="w-3.5 h-3.5" />
                                    </button>
                                    <button type="button"
                                            class="cashier-line-icon cashier-line-icon-danger"
                                            @click="removeLine(line)"
                                            :aria-label="@js(__('cashier.line.remove'))">
                                        <x-icon name="trash" class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div class="cashier-cart-empty" x-show="cart.length === 0" x-cloak>
                        <span class="cashier-cart-empty-icon">
                            <x-icon name="cart" class="w-5 h-5 fg-tertiary" />
                        </span>
                        <div class="cashier-cart-empty-title">{{ __('cashier.cart.empty_title') }}</div>
                        <div class="cashier-cart-empty-sub">{{ __('cashier.cart.empty') }}</div>
                    </div>
                </div>

                {{-- Totals — restructured to match mockup Checkout.html
                     L278–312. Items+units inline with subtotal; discount
                     is a clickable "+ Add discount" line that becomes
                     "Discount X%" once applied; Tax line under that;
                     a divider, then the big "TOTAL DUE" row. --}}
                <div class="cashier-totals">
                    <div class="cashier-totals-row">
                        <span class="cashier-totals-meta">
                            <span x-text="lineCount"></span>
                            <span>{{ __('cashier.totals.items') }}</span>
                            <span class="fg-tertiary">·</span>
                            <span class="tnum" x-text="itemCount"></span>
                            <span>{{ __('cashier.totals.units') }}</span>
                        </span>
                        <span class="num tnum cashier-totals-amount" x-text="money(subtotal)"></span>
                    </div>

                    {{-- "Add discount" line — switches to "Discount X%" /
                         amount when set. Click anywhere on the row to
                         open the discount modal. F3 hint shows when
                         no discount is set. --}}
                    <button type="button"
                            class="cashier-totals-row cashier-totals-disc"
                            :class="{ 'is-set': discount.type }"
                            :disabled="cart.length === 0 || !settings.can_discount"
                            @click="openDiscount()">
                        <span class="cashier-totals-meta">
                            <x-icon name="tag" class="w-3.5 h-3.5" />
                            <span x-show="!discount.type">{{ __('cashier.totals.add_discount') }}</span>
                            <span x-show="discount.type" x-cloak
                                  x-text="@js(__('cashier.totals.discount')) + ' ' + (discount.type === 'pct' ? (discount.value + '%') : money(discount.value))"></span>
                            <span class="cashier-kbd" x-show="!discount.type">F3</span>
                        </span>
                        <span class="num tnum cashier-totals-neg"
                              x-show="discount.type" x-cloak
                              x-text="'−' + money(discountAmt)"></span>
                    </button>

                    {{-- One inline tax row. After the line-total refactor
                         the subtotal already carries every line's tax
                         (inclusive via unit_price, exclusive via
                         `lineTotalDisplay`), so the whole figure is
                         informational — hence "Tax (already included)". --}}
                    <div class="cashier-totals-row cashier-totals-tax"
                         x-show="settings.show_tax_line && tax > 0" x-cloak>
                        <span class="cashier-totals-meta">{{ __('cashier.totals.tax_already_included') }}</span>
                        <span class="num tnum" x-text="money(tax)"></span>
                    </div>

                    <div class="cashier-totals-grand">
                        <span class="cashier-totals-grand-label">{{ __('cashier.totals.grand_due') }}</span>
                        <span class="cashier-totals-grand-amount num tnum" x-text="money(grandTotal)"></span>
                    </div>
                </div>

                {{-- Action row — three equal-width buttons in a single
                     row: Hold · Discount · Charge (primary). Mirrors
                     the canonical mockup's
                     `.mt-4 grid grid-cols-3 gap-2` block in
                     design-system/Checkout.html L314-327. --}}
                <div class="cashier-actions">
                    <button type="button"
                            class="cashier-act-btn"
                            :disabled="cart.length === 0"
                            @click="onHoldClick()">
                        <x-icon name="archive" class="w-4 h-4" />
                        <span>{{ __('cashier.hold.label') }}</span>
                        <span class="cashier-kbd">F4</span>
                    </button>
                    <button type="button"
                            class="cashier-act-btn"
                            :disabled="cart.length === 0 || !settings.can_discount"
                            @click="onDiscountClick()">
                        <x-icon name="tag" class="w-4 h-4" />
                        <span>{{ __('cashier.cart.discount') }}</span>
                        <span class="cashier-kbd">F3</span>
                    </button>
                    <button type="button"
                            class="cashier-act-btn cashier-act-btn-primary"
                            :disabled="cart.length === 0 || hasBlockingOverStock"
                            @click="openPay()">
                        <x-icon name="arrow-right" class="w-4 h-4" />
                        <span>{{ __('cashier.checkout.label') }}</span>
                        <span class="cashier-kbd cashier-kbd-on-accent">F9</span>
                    </button>
                </div>
            </aside>
        </div>

        {{-- Mobile Bottom Navigation --}}
        <nav class="cashier-mobile-nav">
            <button type="button" 
                    class="cashier-mobile-nav-btn" 
                    :class="{ 'is-active': mobileTab === 'catalog' }"
                    @click="mobileTab = 'catalog'">
                <x-icon name="grid" class="w-6 h-6" />
                <span>Products</span>
            </button>
            <button type="button" 
                    class="cashier-mobile-nav-btn" 
                    :class="{ 'is-active': mobileTab === 'cart' }"
                    @click="mobileTab = 'cart'">
                <x-icon name="cart" class="w-6 h-6" />
                <span>Cart</span>
                <span class="cashier-mobile-badge" x-show="lineCount > 0" x-text="lineCount" x-cloak></span>
            </button>
        </nav>

        {{-- ── Payment modal ──────────────────────────────────────── --}}
        <div class="scrim overlay-host"
             x-show="payOpen" x-cloak
             @click.self="closePay()"
             @keydown.escape.window="if (payOpen) closePay()">
            {{-- Payment modal — 2-column layout mirroring mockup
                 Checkout.html L1098-1295. Header on top; left rail of
                 method "rows" (icon + label + sub); right panel adapts
                 to the picked method: cash UI, manual-reference UI, or
                 a generic processing screen. --}}
            <div class="modal-card cashier-pay-modal" role="alertdialog" aria-modal="true">
                <div class="cashier-pay-header">
                    <div>
                        <div class="cashier-pay-header-title">{{ __('cashier.pay.title') }}</div>
                        <div class="cashier-pay-header-sub">
                            <span>{{ __('cashier.cart.draft') }}</span>
                            <span class="fg-tertiary">·</span>
                            <span class="tnum" x-text="itemCount"></span>
                            <span>{{ __('cashier.totals.items') }}</span>
                        </div>
                    </div>
                    <button type="button" class="cashier-icon-btn" @click="closePay()" aria-label="{{ __('cashier.pay.cancel') }}">
                        <x-icon name="x" class="w-5 h-5" />
                    </button>
                </div>

                <div class="cashier-pay-body">
                    {{-- Method list — DB-driven so it respects what the
                         operator has configured in Settings → Payment
                         methods. Each row matches mockup's L1122-1143
                         style: icon square + label + sub-label. --}}
                    <aside class="cashier-pay-methods-list">
                        {{-- Cash methods first (mirrors physical workflow:
                             most rings are cash; QR sits right after as
                             the alternative). --}}
                        <template x-for="m in paymentMethods.filter(x => x.type === 'cash')" :key="m.id">
                            <button type="button"
                                    class="cashier-pay-method-row"
                                    :class="{ 'is-active': payMethodId === m.id && !paySession }"
                                    @click="pickMethod(m)">
                                <span class="cashier-pay-method-icon">
                                    <x-icon name="cash" class="w-4 h-4" />
                                </span>
                                <span class="cashier-pay-method-text">
                                    <span class="cashier-pay-method-name" x-text="m.name"></span>
                                    <span class="cashier-pay-method-sub">{{ __('cashier.pay.method_sub.cash') }}</span>
                                </span>
                            </button>
                        </template>

                        {{-- QR-chooser tile — inline with other methods,
                             styled the same. The customer scans → picks any
                             configured gateway on /pay/pos/{uuid}. --}}
                        <button type="button"
                                class="cashier-pay-method-row"
                                :class="{
                                    'is-active': paySession || payStatus === 'starting' || payStatus === 'paid',
                                    'is-disabled': connectivity?.status === 'offline',
                                }"
                                :disabled="connectivity?.status === 'offline'"
                                @click="payMethodId = null; startPaymentSession()">
                            <span class="cashier-pay-method-icon">
                                <x-icon name="qr-code" class="w-4 h-4" />
                            </span>
                            <span class="cashier-pay-method-text">
                                <span class="cashier-pay-method-name">{{ __('cashier.pay.qr_tile_title') }}</span>
                                <span class="cashier-pay-method-sub"
                                      x-text="connectivity?.status === 'offline'
                                                ? @js(__('cashier.pay.qr_tile_offline'))
                                                : @js(__('cashier.pay.qr_tile_sub'))"></span>
                            </span>
                        </button>

                        {{-- Apple Pay / Google Pay — same Stripe Checkout
                             Session either way (Stripe has no separate wallet
                             payment-method type; the wallet button just
                             surfaces automatically on the customer's own
                             device). These skip the gateway chooser and go
                             straight to Stripe — see payWithWallet(). Hidden
                             entirely when Stripe isn't configured. --}}
                        <template x-if="stripeMethodId">
                            <button type="button"
                                    class="cashier-pay-method-row"
                                    :class="{
                                        'is-active': walletBrand === 'apple_pay' && (paySession || payStatus === 'starting'),
                                        'is-disabled': connectivity?.status === 'offline',
                                    }"
                                    :disabled="connectivity?.status === 'offline'"
                                    @click="payWithWallet('apple_pay')">
                                <span class="cashier-pay-method-icon">
                                    <x-icon name="card" class="w-4 h-4" />
                                </span>
                                <span class="cashier-pay-method-text">
                                    <span class="cashier-pay-method-name">{{ __('cashier.pay.apple_pay_title') }}</span>
                                    <span class="cashier-pay-method-sub">{{ __('cashier.pay.wallet_sub') }}</span>
                                </span>
                            </button>
                        </template>
                        <template x-if="stripeMethodId">
                            <button type="button"
                                    class="cashier-pay-method-row"
                                    :class="{
                                        'is-active': walletBrand === 'google_pay' && (paySession || payStatus === 'starting'),
                                        'is-disabled': connectivity?.status === 'offline',
                                    }"
                                    :disabled="connectivity?.status === 'offline'"
                                    @click="payWithWallet('google_pay')">
                                <span class="cashier-pay-method-icon">
                                    <x-icon name="card" class="w-4 h-4" />
                                </span>
                                <span class="cashier-pay-method-text">
                                    <span class="cashier-pay-method-name">{{ __('cashier.pay.google_pay_title') }}</span>
                                    <span class="cashier-pay-method-sub">{{ __('cashier.pay.wallet_sub') }}</span>
                                </span>
                            </button>
                        </template>

                        {{-- Remaining (non-cash) methods. --}}
                        <template x-for="m in paymentMethods.filter(x => x.type !== 'cash')" :key="m.id">
                            <button type="button"
                                    class="cashier-pay-method-row"
                                    :class="{ 'is-active': payMethodId === m.id && !paySession }"
                                    @click="pickMethod(m)">
                                <span class="cashier-pay-method-icon">
                                    <x-icon name="card" class="w-4 h-4" />
                                </span>
                                <span class="cashier-pay-method-text">
                                    <span class="cashier-pay-method-name" x-text="m.name"></span>
                                    <span class="cashier-pay-method-sub"
                                          x-text="m.requires_reference ? @js(__('cashier.pay.method_sub.ref')) : @js(__('cashier.pay.method_sub.terminal'))"></span>
                                </span>
                            </button>
                        </template>
                    </aside>

                    <section class="cashier-pay-detail">
                        {{-- Total Due row — mockup L1148-1151. The amount
                             shows what's STILL owed (`payRemaining`) so
                             split tender feels right: pay $30, this row
                             updates to "$20.00 due"; pay the rest, "$0.00". --}}
                        <div class="cashier-pay-detail-due">
                            <span class="cashier-pay-detail-due-label">{{ __('cashier.totals.grand_due') }}</span>
                            <span class="cashier-pay-detail-due-amount num tnum"
                                  x-text="money(payments.length > 0 ? payRemaining : grandTotal)"></span>
                        </div>

                        {{-- Split-tender tally: one row per committed
                             payment, with a ✕ to drop it. Hidden when
                             empty (the most-common single-tender flow). --}}
                        <div class="cashier-pay-tally"
                             x-show="payments.length > 0" x-cloak>
                            <div class="cashier-pay-tally-head">
                                <span>{{ __('cashier.pay.split_so_far') }}</span>
                                <span class="num tnum" x-text="money(paymentsTotal)"></span>
                            </div>
                            <template x-for="(p, idx) in payments" :key="idx">
                                <div class="cashier-pay-tally-row">
                                    <span class="cashier-pay-tally-icon">
                                        <x-icon name="cash" class="w-4 h-4" x-show="p.method_type === 'cash'" />
                                        <x-icon name="card" class="w-4 h-4" x-show="p.method_type !== 'cash'" />
                                    </span>
                                    <span class="cashier-pay-tally-name" x-text="p.method_name"></span>
                                    <span class="cashier-pay-tally-ref mono"
                                          x-show="p.reference" x-cloak
                                          x-text="p.reference"></span>
                                    <span class="cashier-pay-tally-amount num tnum"
                                          x-text="money(p.amount)"></span>
                                    <button type="button"
                                            class="cashier-pay-tally-remove"
                                            :disabled="submitting"
                                            @click="removeCommittedPayment(idx)"
                                            :aria-label="@js(__('cashier.pay.remove_payment_aria'))"
                                            :title="@js(__('cashier.pay.remove_payment_aria'))">
                                        <x-icon name="x" class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </template>
                        </div>

                        {{-- Credit-mode banner — visible when customer
                             is attached AND tendered < grand. Tells
                             the cashier the unpaid portion will land
                             on the customer's outstanding balance. --}}
                        <div class="cashier-pay-credit-banner"
                             x-show="isCreditMode" x-cloak>
                            <x-icon name="cash" class="w-4 h-4" />
                            <span>{{ __('cashier.pay.credit_balance') }}</span>
                            <span class="num tnum font-semibold" x-text="money(balanceDue)"></span>
                        </div>

                        {{-- Cash UI — when picked method type is cash --}}
                        <template x-if="pickedMethod && pickedMethod.type === 'cash' && !paySession && payStatus !== 'starting'">
                            <div class="cashier-pay-pane">
                                <div class="cashier-pay-eyebrow">{{ __('cashier.pay.cash_given') }}</div>
                                <div class="cashier-pay-cash-wrap">
                                    <span class="cashier-pay-cash-prefix" x-text="@js(app_currency()['symbol'] ?? '$')"></span>
                                    <input type="number"
                                           data-cashier-tendered
                                           x-model="payTendered"
                                           class="cashier-pay-cash-input num tnum"
                                           step="0.01" min="0"
                                           @keydown.enter.prevent="canComplete && complete()">
                                </div>
                                <div class="cashier-pay-quick">
                                    <template x-for="amt in quickTenderAmounts" :key="amt">
                                        <button type="button" class="cashier-pay-quick-tile" @click="quickTender(amt)">
                                            <span class="num tnum" x-text="money(amt)"></span>
                                        </button>
                                    </template>
                                </div>
                                <div class="cashier-pay-change-card">
                                    <span class="cashier-pay-change-label">{{ __('cashier.pay.change_due') }}</span>
                                    <span class="cashier-pay-change-amount num tnum"
                                          :class="{ 'is-pos': changeDue > 0 }"
                                          x-text="money(changeDue)"></span>
                                </div>
                                {{-- Two side-by-side buttons in split-tender
                                     mode: "Add payment" appends to the tally,
                                     "Complete sale" submits. In single-tender
                                     mode (most sales) only the right button
                                     shows — keeping the one-click flow. --}}
                                <div class="cashier-pay-actions">
                                    <button type="button"
                                            class="cashier-pay-add"
                                            x-show="!willCompleteCurrent && canAddCurrent"
                                            x-cloak
                                            :disabled="submitting"
                                            @click="addCurrentPayment()">
                                        <x-icon name="plus" class="w-4 h-4" />
                                        <span>{{ __('cashier.pay.add_payment') }}</span>
                                    </button>
                                    <button type="button"
                                            class="cashier-pay-accept"
                                            :disabled="!canComplete || submitting"
                                            @click="complete()">
                                        <svg x-show="submitting" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                        </svg>
                                        <span x-text="submitting
                                            ? @js(__('cashier.pay.completing'))
                                            : (payments.length > 0 || isCreditMode
                                                ? @js(__('cashier.pay.complete'))
                                                : @js(__('cashier.pay.accept_cash')))"></span>
                                    </button>
                                </div>
                            </div>
                        </template>

                        {{-- Manual-reference UI (card / UPI / cheque) — when
                             picked method requires a reference but isn't cash --}}
                        <template x-if="pickedMethod && pickedMethod.type !== 'cash' && pickedMethod.requires_reference && !paySession && payStatus !== 'starting'">
                            <div class="cashier-pay-pane">

                                {{-- UPI VPA: customer scans this QR and
                                     pays in their UPI app. We have no
                                     webhook for VPA payments, so the
                                     cashier still confirms manually via
                                     the reference (UTR) field below. --}}
                                <template x-if="pickedMethod && pickedMethod.code === 'upi' && pickedMethod.vpa">
                                    <div class="upi-qr-pane">
                                        <div class="upi-qr-head">
                                            {{ __('cashier.pay.upi_scan_title') }}
                                        </div>
                                        <div class="upi-qr-art">
                                            <template x-if="upiQrDataUrl">
                                                <img :src="upiQrDataUrl" alt="UPI QR" width="220" height="220">
                                            </template>
                                        </div>
                                        <div class="upi-qr-vpa mono" x-text="pickedMethod.vpa"></div>
                                        <div class="upi-qr-hint">{{ __('cashier.pay.upi_scan_hint') }}</div>
                                    </div>
                                </template>

                                <div class="cashier-pay-eyebrow">{{ __('cashier.pay.reference') }}</div>
                                <input type="text"
                                       x-model="payReference"
                                       class="pos-input mono cashier-pay-ref-input"
                                       maxlength="191"
                                       placeholder="{{ __('cashier.pay.reference_placeholder') }}">
                                {{-- Amount input — visible only when split
                                     tender is live (otherwise the full
                                     remaining is charged automatically). --}}
                                <label class="field cashier-pay-amount-field"
                                       x-show="payments.length > 0 || (parseFloat(payTendered) || 0) < payRemaining"
                                       x-cloak>
                                    <span class="field-label">{{ __('cashier.pay.amount_charged') }}</span>
                                    <div class="cashier-pay-cash-wrap">
                                        <span class="cashier-pay-cash-prefix" x-text="@js(app_currency()['symbol'] ?? '$')"></span>
                                        <input type="number"
                                               x-model="payTendered"
                                               class="cashier-pay-cash-input num tnum"
                                               step="0.01" min="0">
                                    </div>
                                </label>
                                <div class="cashier-pay-hint">{{ __('cashier.pay.ref_hint') }}</div>
                                <div class="cashier-pay-actions">
                                    <button type="button"
                                            class="cashier-pay-add"
                                            x-show="!willCompleteCurrent && canAddCurrent"
                                            x-cloak
                                            :disabled="submitting"
                                            @click="addCurrentPayment()">
                                        <x-icon name="plus" class="w-4 h-4" />
                                        <span>{{ __('cashier.pay.add_payment') }}</span>
                                    </button>
                                    <button type="button"
                                            class="cashier-pay-accept"
                                            :disabled="!canComplete || submitting"
                                            @click="complete()">
                                        <svg x-show="submitting" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                        </svg>
                                        <span x-text="submitting
                                            ? @js(__('cashier.pay.completing'))
                                            : (payments.length > 0 || isCreditMode
                                                ? @js(__('cashier.pay.complete'))
                                                : @js(__('cashier.pay.take_payment')))"></span>
                                    </button>
                                </div>
                            </div>
                        </template>

                        {{-- QR-chooser pane (provider-agnostic).
                             Cashier hits "Charge via QR" → backend
                             creates a pos_payment_sessions row →
                             cashier shows QR pointing at our domain →
                             customer picks any wired gateway on the
                             /pay/pos/{uuid} page → pays → cashier sees
                             "paid" via polling → sale auto-completes. --}}
                        <template x-if="paySession || payStatus === 'starting'">
                            <div class="cashier-pay-pane pay-qr">
                                <div class="pay-qr-head">
                                    <span class="pay-qr-status-dot"
                                          :class="{
                                              'is-pending': payStatus === 'pending' || payStatus === 'starting' || payStatus === 'selected',
                                              'is-paid':    payStatus === 'paid',
                                              'is-failed':  payStatus === 'failed' || payStatus === 'expired' || payStatus === 'cancelled',
                                          }"></span>
                                    <span class="pay-qr-head-text" x-text="
                                        payStatus === 'starting' ? @js(__('cashier.pay.qr_starting')) :
                                        payStatus === 'pending'  ? @js(__('cashier.pay.qr_waiting'))  :
                                        payStatus === 'selected' ? @js(__('cashier.pay.qr_selected')) :
                                        payStatus === 'paid'     ? @js(__('cashier.pay.qr_paid'))     :
                                        payStatus === 'expired'  ? @js(__('cashier.pay.qr_expired'))  :
                                        payStatus === 'cancelled'? @js(__('cashier.pay.qr_cancelled')):
                                        payStatus === 'failed'   ? @js(__('cashier.pay.qr_failed'))   :
                                                                   @js(__('cashier.pay.qr_idle'))"></span>
                                </div>

                                <div class="pay-qr-body">
                                    <div class="pay-qr-art"
                                         :class="{ 'is-dead': ['expired','cancelled','failed'].includes(payStatus) }">
                                        <template x-if="payQrDataUrl">
                                            <img :src="payQrDataUrl" alt="Payment QR" width="240" height="240">
                                        </template>
                                        <template x-if="!payQrDataUrl">
                                            <div class="pay-qr-art-placeholder">
                                                <x-icon name="qr-code" class="w-10 h-10" />
                                            </div>
                                        </template>
                                        {{-- Dead-link overlay — covers the QR once the session
                                             is expired / cancelled / failed so the cashier can't
                                             keep waving an un-payable code at the customer. --}}
                                        <div class="pay-qr-dead-overlay"
                                             x-show="['expired','cancelled','failed'].includes(payStatus)"
                                             x-cloak>
                                            <x-icon name="alert" class="w-9 h-9" />
                                            <span x-text="
                                                payStatus === 'expired'  ? @js(__('cashier.pay.qr_expired'))  :
                                                payStatus === 'cancelled'? @js(__('cashier.pay.qr_cancelled')):
                                                                           @js(__('cashier.pay.qr_failed'))"></span>
                                        </div>
                                    </div>

                                    <div class="pay-qr-meta">
                                        <div class="pay-qr-instructions"
                                             x-text="['expired','cancelled','failed'].includes(payStatus)
                                                        ? @js(__('cashier.pay.qr_dead_hint'))
                                                        : @js(__('cashier.pay.qr_instructions'))"></div>

                                        <div class="pay-qr-share-wrap"
                                             x-show="!['expired','cancelled','failed'].includes(payStatus)">
                                            <div class="pay-qr-row-label">{{ __('cashier.pay.qr_share_link') }}</div>
                                            <div class="pay-qr-share">
                                                <input type="text"
                                                       readonly
                                                       class="pay-qr-share-input mono"
                                                       :value="paySession?.pay_url || ''"
                                                       @click="$event.target.select()">
                                                <a class="pay-qr-share-copy"
                                                   :href="paySession?.pay_url || '#'"
                                                   target="_blank"
                                                   rel="noopener"
                                                   :title="@js(__('cashier.pay.qr_open'))">
                                                    <x-icon name="external" class="w-4 h-4" />
                                                </a>
                                                <button type="button"
                                                        class="pay-qr-share-copy"
                                                        @click="copyPayUrl()"
                                                        :title="@js(__('cashier.pay.qr_copy'))">
                                                    <x-icon name="copy" class="w-4 h-4" />
                                                </button>
                                            </div>
                                        </div>

                                        <button type="button"
                                                class="pay-qr-cancel"
                                                x-show="payStatus === 'pending' || payStatus === 'selected' || payStatus === 'starting'"
                                                x-cloak
                                                @click="cancelPaymentSession()">
                                            {{ __('cashier.pay.qr_cancel') }}
                                        </button>

                                        {{-- When the link is dead, offer a one-tap regenerate
                                             (mints a fresh session) instead of leaving the
                                             cashier stuck on an un-payable QR. --}}
                                        <button type="button"
                                                class="pay-qr-regenerate"
                                                x-show="['expired','cancelled','failed'].includes(payStatus)"
                                                x-cloak
                                                @click="payMethodId = null; startPaymentSession()">
                                            {{ __('cashier.pay.qr_regenerate') }}
                                        </button>
                                    </div>
                                </div>

                                <div class="pay-qr-foot"
                                     x-show="payCountdown && (payStatus === 'pending' || payStatus === 'selected' || payStatus === 'starting')"
                                     x-cloak>
                                    {{ __('cashier.pay.qr_expires_in') }}
                                    <span class="tnum" x-text="payCountdown"></span>
                                </div>
                            </div>
                        </template>


                        {{-- Generic terminal/processing UI — when method is
                             not cash and doesn't require a reference AND isn't
                             a wired-gateway provider. --}}
                        <template x-if="pickedMethod && pickedMethod.type !== 'cash' && !pickedMethod.requires_reference && !paySession && payStatus !== 'starting'">
                            <div class="cashier-pay-pane cashier-pay-pane-centered">
                                <div class="cashier-pay-icon-circle">
                                    <x-icon name="card" class="w-8 h-8" />
                                </div>
                                <div class="cashier-pay-headline">{{ __('cashier.pay.terminal_title') }}</div>
                                <div class="cashier-pay-subhead">{{ __('cashier.pay.terminal_sub') }}</div>
                                {{-- Optional amount field for split tender
                                     on a no-reference card terminal. --}}
                                <label class="field cashier-pay-amount-field"
                                       x-show="payments.length > 0 || (parseFloat(payTendered) || 0) < payRemaining"
                                       x-cloak>
                                    <span class="field-label">{{ __('cashier.pay.amount_charged') }}</span>
                                    <div class="cashier-pay-cash-wrap">
                                        <span class="cashier-pay-cash-prefix" x-text="@js(app_currency()['symbol'] ?? '$')"></span>
                                        <input type="number"
                                               x-model="payTendered"
                                               class="cashier-pay-cash-input num tnum"
                                               step="0.01" min="0">
                                    </div>
                                </label>
                                <div class="cashier-pay-actions">
                                    <button type="button"
                                            class="cashier-pay-add"
                                            x-show="!willCompleteCurrent && canAddCurrent"
                                            x-cloak
                                            :disabled="submitting"
                                            @click="addCurrentPayment()">
                                        <x-icon name="plus" class="w-4 h-4" />
                                        <span>{{ __('cashier.pay.add_payment') }}</span>
                                    </button>
                                    <button type="button"
                                            class="cashier-pay-accept cashier-pay-accept-inline"
                                            :disabled="!canComplete || submitting"
                                            @click="complete()">
                                        <span x-text="submitting
                                            ? @js(__('cashier.pay.completing'))
                                            : (payments.length > 0 || isCreditMode
                                                ? @js(__('cashier.pay.complete'))
                                                : @js(__('cashier.pay.send_terminal')))"></span>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </section>
                </div>
            </div>
        </div>

        {{-- ── Variant picker modal ──────────────────────────────── --}}
        <div class="scrim overlay-host"
             x-show="variantPickerOpen" x-cloak
             @click.self="closeVariantPicker()"
             @keydown.escape.window="if (variantPickerOpen) closeVariantPicker()">
            <div class="modal-card cashier-variant-card" role="dialog" aria-modal="true">
                <div class="modal-body">
                    <div class="cashier-customer-head">
                        <div class="cashier-variant-title">
                            <div class="text-base font-semibold" x-text="variantPickerProduct?.name"></div>
                            <div class="cashier-variant-sub">{{ __('cashier.variant.pick_sub') }}</div>
                        </div>
                        <button type="button" class="cashier-icon-btn" @click="closeVariantPicker()" aria-label="{{ __('cashier.pay.cancel') }}">
                            <x-icon name="x" class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="cashier-variant-grid">
                        <template x-for="v in (variantPickerProduct?.variants ?? [])" :key="v.id">
                            {{-- Stock state, three cases (only when the parent
                                 product tracks stock — services / kits with
                                 track_stock=false skip every guard below):
                                   • on_hand === null  → "not listed yet"
                                     (never received). Tile is disabled with a
                                     "Stock not listed yet" title + badge.
                                   • on_hand <= 0      → "out of stock". Same
                                     disable + a danger badge.
                                   • otherwise          → live qty badge. --}}
                            <button type="button"
                                    class="cashier-variant-tile"
                                    :class="{
                                        'is-out':         variantPickerProduct?.track_stock && v.on_hand !== null && parseFloat(v.on_hand) <= 0,
                                        'is-unavailable': variantPickerProduct?.track_stock && (v.on_hand === null || v.on_hand === undefined),
                                    }"
                                    :disabled="variantPickerProduct?.track_stock && (v.on_hand === null || v.on_hand === undefined || parseFloat(v.on_hand) <= 0)"
                                    :title="!variantPickerProduct?.track_stock ? ''
                                            : (v.on_hand === null || v.on_hand === undefined) ? @js(__('cashier.stock.not_listed'))
                                            : (parseFloat(v.on_hand) <= 0) ? @js(__('cashier.stock.out'))
                                            : ''"
                                    @click="pickVariant(v)">
                                <div class="cashier-variant-tile-label" x-text="v.label"></div>
                                <div class="cashier-variant-tile-sku mono" x-text="v.sku"></div>
                                <div class="cashier-variant-tile-price-row">
                                    <span class="cashier-variant-tile-price num tnum" x-text="money(v.selling_price)"></span>
                                    <template x-if="v.mrp && parseFloat(v.mrp) > parseFloat(v.selling_price)">
                                        <span class="cashier-tile-mrp num tnum" x-text="money(v.mrp)"></span>
                                    </template>
                                </div>
                                <div class="cashier-variant-tile-stock"
                                     x-show="variantPickerProduct?.track_stock" x-cloak
                                     :class="{
                                        'is-out':         v.on_hand !== null && v.on_hand !== undefined && parseFloat(v.on_hand) <= 0,
                                        'is-low':         v.on_hand !== null && parseFloat(v.on_hand) > 0 && parseFloat(v.on_hand) <= 5,
                                        'is-unavailable': v.on_hand === null || v.on_hand === undefined,
                                     }">
                                    <span x-text="(v.on_hand === null || v.on_hand === undefined) ? @js(__('cashier.stock.not_listed'))
                                                  : parseFloat(v.on_hand) <= 0 ? @js(__('cashier.stock.out'))
                                                  : (v.on_hand + ' ' + @js(__('cashier.variant.in_stock')))"></span>
                                </div>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Batch picker modal (pharmacy / FEFO) ──────────────
             Opens automatically when the cashier adds a batch-tracked
             product to the cart. Lists every live batch (qty > 0) for
             the current store, sorted FEFO. Each tile shows batch_number,
             expiry (with "X days left" colour-coded), on_hand qty, and
             the batch's selling price + MRP. --}}
        <div class="scrim overlay-host"
             x-show="batchPickerOpen" x-cloak
             @click.self="closeBatchPicker()"
             @keydown.escape.window="if (batchPickerOpen) closeBatchPicker()">
            <div class="modal-card cashier-variant-card" role="dialog" aria-modal="true">
                <div class="modal-body">
                    <div class="cashier-customer-head">
                        <div class="cashier-variant-title">
                            <div class="text-base font-semibold">
                                <span x-text="batchPickerProduct?.name ?? ''"></span>
                                <template x-if="batchPickerVariant?.label">
                                    <span class="fg-tertiary"> · <span x-text="batchPickerVariant.label"></span></span>
                                </template>
                            </div>
                            <div class="cashier-variant-sub">{{ __('cashier.batch.pick_sub') }}</div>
                        </div>
                        <button type="button" class="cashier-icon-btn" @click="closeBatchPicker()" aria-label="{{ __('cashier.pay.cancel') }}">
                            <x-icon name="x" class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="cashier-batch-grid">
                        <template x-for="b in batchPickerResults" :key="b.id">
                            <button type="button"
                                    class="cashier-batch-tile"
                                    :class="{
                                        'is-expired': batchDaysToExpiry(b) !== null && batchDaysToExpiry(b) < 0,
                                        'is-soon':    batchDaysToExpiry(b) !== null && batchDaysToExpiry(b) >= 0 && batchDaysToExpiry(b) <= 30,
                                        'is-blocked': isBatchBlocked(b),
                                    }"
                                    :disabled="isBatchBlocked(b)"
                                    :title="isBatchBlocked(b) ? @js(__('cashier.batch.blocked_tooltip')) : ''"
                                    @click="pickBatch(b)">
                                <div class="cashier-batch-tile-head">
                                    <span class="cashier-batch-tile-num mono" x-text="b.batch_number"></span>
                                    <span class="cashier-batch-tile-stock tnum"
                                          x-text="b.on_hand + ' ' + (batchPickerProduct?.unit ?? '')"></span>
                                </div>
                                <div class="cashier-batch-tile-expiry"
                                     x-show="b.expiry_date" x-cloak>
                                    <span x-text="b.expiry_date"></span>
                                    <span class="cashier-batch-tile-days"
                                          x-show="batchDaysToExpiry(b) !== null"
                                          x-cloak
                                          x-text="
                                              batchDaysToExpiry(b) < 0
                                                  ? @js(__('cashier.batch.expired'))
                                                  : (batchDaysToExpiry(b) === 0
                                                      ? @js(__('cashier.batch.expires_today'))
                                                      : (@js(__('cashier.batch.days_left')).replace(':n', batchDaysToExpiry(b))))"></span>
                                </div>
                                <div class="cashier-batch-tile-prices">
                                    <span class="cashier-batch-tile-price num tnum"
                                          x-text="money(b.selling_price ?? (batchPickerVariant?.selling_price ?? batchPickerProduct?.selling_price ?? 0))"></span>
                                    <template x-if="b.mrp && parseFloat(b.mrp) > 0">
                                        <span class="cashier-tile-mrp num tnum"
                                              x-text="@js(__('cashier.batch.mrp_prefix')) + ' ' + money(b.mrp)"></span>
                                    </template>
                                </div>
                            </button>
                        </template>

                        <div class="cashier-grid-empty"
                             x-show="!batchPickerLoading && batchPickerResults.length === 0"
                             x-cloak>
                            <x-icon name="search" class="w-10 h-10 fg-tertiary" />
                            <div class="cashier-grid-empty-title">{{ __('cashier.batch.empty_title') }}</div>
                            <div class="cashier-grid-empty-sub">{{ __('cashier.batch.empty_sub') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Weight prompt modal (sold-by-weight items) ────────── --}}
        <div class="scrim overlay-host"
             x-show="weightPromptOpen" x-cloak
             @click.self="cancelWeightPrompt()"
             @keydown.escape.window="if (weightPromptOpen) cancelWeightPrompt()">
            <div class="modal-card cashier-weight-card" role="dialog" aria-modal="true">
                <div class="modal-body">
                    <div class="cashier-customer-head">
                        <div class="cashier-variant-title">
                            <div class="text-base font-semibold" x-text="weightPromptName"></div>
                            <div class="cashier-variant-sub">{{ __('cashier.weight.sub') }}</div>
                        </div>
                        <button type="button" class="cashier-icon-btn" @click="cancelWeightPrompt()" aria-label="{{ __('cashier.pay.cancel') }}">
                            <x-icon name="x" class="w-5 h-5" />
                        </button>
                    </div>

                    <label class="field">
                        <span class="field-label">{{ __('cashier.weight.label') }}</span>
                        <div class="cashier-weight-input-wrap">
                            <input type="number"
                                   x-ref="weightField"
                                   class="pos-input cashier-weight-input"
                                   x-model="weightInput"
                                   @keydown.enter.prevent="confirmWeight()"
                                   min="0" step="any" inputmode="decimal"
                                   autocomplete="off" placeholder="0">
                            <span class="cashier-weight-unit" x-text="weightPromptUnit"></span>
                        </div>
                    </label>

                    <div class="cashier-weight-preview">
                        <span class="fg-tertiary">{{ __('cashier.weight.line_total') }}</span>
                        <span class="num tnum font-semibold" x-text="money(weightPromptTotal)"></span>
                    </div>
                </div>
                <div class="modal-foot">
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="cancelWeightPrompt()">
                        {{ __('cashier.pay.cancel') }}
                    </button>
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-primary" @click="confirmWeight()">
                        <x-icon name="check" class="w-4 h-4" />
                        {{ __('cashier.weight.add') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Camera scanner modal (ZXing) ─────────────────────── --}}
        <div class="scrim overlay-host"
             x-show="cameraOpen" x-cloak
             @click.self="closeCamera()"
             @keydown.escape.window="if (cameraOpen) closeCamera()">
            <div class="modal-card cashier-camera-card" role="dialog" aria-modal="true">
                <div class="modal-body">
                    <div class="cashier-customer-head">
                        <div class="cashier-variant-title">
                            <div class="text-base font-semibold">{{ __('cashier.scan.camera_title') }}</div>
                            <div class="cashier-variant-sub">{{ __('cashier.scan.camera_sub') }}</div>
                        </div>
                        <button type="button" class="cashier-icon-btn" @click="closeCamera()" aria-label="{{ __('cashier.pay.cancel') }}">
                            <x-icon name="x" class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="cashier-camera-stage">
                        {{-- ZXing draws the live feed here; muted+playsinline so
                             mobile browsers autoplay without going fullscreen. --}}
                        <video x-ref="cameraVideo" class="cashier-camera-video" muted autoplay playsinline></video>
                    </div>

                    <p class="cashier-camera-error" x-show="cameraError" x-cloak x-text="cameraError"></p>
                </div>
                <div class="modal-foot">
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="closeCamera()">
                        {{ __('cashier.pay.cancel') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Customer picker modal ─────────────────────────────── --}}
        <div class="scrim overlay-host"
             x-show="customerPickerOpen" x-cloak
             @click.self="closeCustomerPicker()"
             @keydown.escape.window="if (customerPickerOpen) closeCustomerPicker()">
            <div class="modal-card cashier-cust-picker-card" role="dialog" aria-modal="true">
                <div class="modal-body">
                    <div class="cashier-customer-head">
                        <div class="text-base font-semibold">{{ __('cashier.customer.pick_title') }}</div>
                        <button type="button" class="cashier-icon-btn" @click="closeCustomerPicker()" aria-label="{{ __('cashier.pay.cancel') }}">
                            <x-icon name="x" class="w-5 h-5" />
                        </button>
                    </div>
                    <label class="field">
                        <input type="text"
                               data-customer-picker-input
                               class="pos-input"
                               x-model="customerPickerQuery"
                               @input="_searchCustomersDebounced()"
                               autocomplete="off"
                               spellcheck="false"
                               placeholder="{{ __('cashier.customer.pick_placeholder') }}">
                    </label>

                    <div class="cashier-customer-results">
                        <template x-for="c in customerPickerResults" :key="c.id">
                            <button type="button"
                                    class="cashier-customer-result"
                                    @click="selectCustomer(c)">
                                <div class="cashier-customer-result-meta">
                                    <div class="cashier-customer-result-name" x-text="c.name"></div>
                                    <div class="cashier-customer-result-sub mono">
                                        <span x-text="$formatPhone(c.phone) || c.code || '—'"></span>
                                    </div>
                                </div>
                                <div class="cashier-customer-result-balance num tnum"
                                     x-show="parseFloat(c.outstanding_balance) > 0" x-cloak>
                                    <span x-text="money(c.outstanding_balance)"></span>
                                    <span class="ms-1 fg-tertiary">{{ __('cashier.customer.outstanding_short') }}</span>
                                </div>
                            </button>
                        </template>

                        <div class="cashier-customer-empty"
                             x-show="!customerPickerLoading && customerPickerResults.length === 0" x-cloak>
                            {{ __('cashier.customer.pick_empty') }}
                        </div>
                    </div>
                </div>
                <div class="modal-foot">
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="closeCustomerPicker()">
                        {{ __('cashier.pay.cancel') }}
                    </button>
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-primary" @click="openCustomerAdd()">
                        <x-icon name="plus" class="w-4 h-4" />
                        {{ __('cashier.customer.add_new') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Quick-add customer modal ──────────────────────────── --}}
        <div class="scrim overlay-host"
             x-show="customerAddOpen" x-cloak
             @click.self="closeCustomerAdd()"
             @keydown.escape.window="if (customerAddOpen) closeCustomerAdd()">
            <div class="modal-card cashier-customer-add-card" role="dialog" aria-modal="true">
                <div class="modal-body">
                    <div class="cashier-customer-head">
                        <div class="text-base font-semibold">{{ __('cashier.customer.add_title') }}</div>
                        <button type="button" class="cashier-icon-btn" @click="closeCustomerAdd()" aria-label="{{ __('cashier.pay.cancel') }}">
                            <x-icon name="x" class="w-5 h-5" />
                        </button>
                    </div>
                    <div class="form-stack">
                        <label class="field">
                            <span class="field-label is-required">{{ __('cashier.customer.add_name') }}</span>
                            <input type="text"
                                   data-customer-add-name
                                   class="pos-input"
                                   x-model="customerAddForm.name"
                                   @keydown.enter.prevent="submitCustomerAdd()"
                                   maxlength="191"
                                   autocomplete="off">
                        </label>
                        <label class="field">
                            <span class="field-label">{{ __('cashier.customer.add_phone') }}</span>
                            {{-- `js-phone` is the detector class for
                                 lib/phone-input.js — it auto-upgrades to
                                 intl-tel-input (flag + dial-code dropdown
                                 + libphonenumber formatting). MutationObserver
                                 catches the input when the modal opens
                                 (it's hidden via x-show, not removed from
                                 the DOM, so a one-shot attach is enough). --}}
                            {{-- NOT x-modeled: intl-tel-input and x-model fight
                                 and the field renders as "[object Object]". The
                                 submit handler reads the canonical E.164 straight
                                 off the intl-tel-input instance instead. --}}
                            <input type="tel"
                                   class="pos-input mono js-phone"
                                   maxlength="32"
                                   autocomplete="off">
                        </label>
                        <label class="field">
                            <span class="field-label">{{ __('cashier.customer.add_email') }}</span>
                            <input type="email"
                                   class="pos-input"
                                   x-model="customerAddForm.email"
                                   maxlength="191"
                                   autocomplete="off">
                        </label>
                    </div>
                </div>
                <div class="modal-foot">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            :disabled="customerAddSubmitting"
                            @click="closeCustomerAdd()">
                        {{ __('cashier.customer.add_cancel') }}
                    </button>
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-primary"
                            :disabled="customerAddSubmitting || !customerAddForm.name?.trim()"
                            @click="submitCustomerAdd()">
                        <svg x-show="customerAddSubmitting" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                        </svg>
                        {{ __('cashier.customer.add_save') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Line note modal — mirrors mockup Checkout.html L950-975 ─── --}}
        <div class="scrim overlay-host"
             x-show="noteLineId !== null" x-cloak
             @click.self="closeNote()"
             @keydown.escape.window="if (noteLineId !== null) closeNote()">
            <div class="modal-card cashier-note-card" role="dialog" aria-modal="true">
                <div class="modal-body">
                    <div class="cashier-customer-head">
                        <div class="cashier-variant-title">
                            <div class="text-base font-semibold">{{ __('cashier.line.note_title') }}</div>
                            <div class="cashier-variant-sub" x-text="noteLine?.name ?? ''"></div>
                        </div>
                        <button type="button" class="cashier-icon-btn" @click="closeNote()" aria-label="{{ __('cashier.pay.cancel') }}">
                            <x-icon name="x" class="w-5 h-5" />
                        </button>
                    </div>
                    <textarea data-cashier-note
                              class="pos-input cashier-note-textarea"
                              x-model="noteText"
                              maxlength="500"
                              placeholder="{{ __('cashier.line.note_placeholder') }}"></textarea>
                </div>
                <div class="modal-foot">
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="closeNote()">
                        {{ __('cashier.pay.cancel') }}
                    </button>
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-primary" @click="saveNote()">
                        <x-icon name="check" class="w-4 h-4" />
                        {{ __('cashier.line.note_save') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Discount modal ────────────────────────────────────
             Mirrors mockup Checkout.html L911-948. Two tabs (Percent
             vs Amount), preset chips, an input with a $/% prefix, a
             "New total" preview, and Remove + Apply buttons in the
             footer. State lives on the cashier-page Alpine factory
             via `_discountForm` so it survives modal open/close. --}}
        <div class="scrim overlay-host"
             x-show="showDiscount" x-cloak
             @click.self="closeDiscount()"
             @keydown.escape.window="if (showDiscount) closeDiscount()">
            <div class="modal-card cashier-disc-card" role="dialog" aria-modal="true">
                <div class="modal-body">
                    <div class="cashier-disc-head">
                        <div class="text-base font-semibold">{{ __('cashier.discount.title') }}</div>
                        <button type="button" class="cashier-icon-btn" @click="closeDiscount()" aria-label="{{ __('cashier.pay.cancel') }}">
                            <x-icon name="x" class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="cashier-seg cashier-disc-seg" role="tablist">
                        <button type="button"
                                :class="{ 'is-active': _discountForm.kind === 'pct' }"
                                @click="_discountForm.kind = 'pct'">
                            {{ __('cashier.discount.percent') }}
                        </button>
                        <button type="button"
                                :class="{ 'is-active': _discountForm.kind === 'amt' }"
                                @click="_discountForm.kind = 'amt'">
                            {{ __('cashier.discount.amount') }}
                        </button>
                    </div>

                    <div class="cashier-disc-presets" x-show="_discountForm.kind === 'pct'" x-cloak>
                        <template x-for="p in [5, 10, 15, 20]" :key="p">
                            <button type="button"
                                    class="cashier-disc-preset tnum"
                                    :class="{ 'is-active': parseFloat(_discountForm.val) === p }"
                                    @click="_discountForm.val = p"
                                    x-text="p + '%'"></button>
                        </template>
                    </div>

                    <label class="field cashier-disc-field">
                        <span class="field-label"
                              x-text="_discountForm.kind === 'pct' ? @js(__('cashier.discount.percent_off')) : @js(__('cashier.discount.amount_off'))"></span>
                        <div class="cashier-disc-input-wrap">
                            <span class="cashier-disc-input-prefix"
                                  x-text="_discountForm.kind === 'pct' ? '%' : @js(app_currency()['symbol'] ?? '$')"></span>
                            <input type="number"
                                   class="pos-input cashier-disc-input tnum"
                                   step="0.01"
                                   min="0"
                                   :max="_discountForm.kind === 'pct' ? 100 : (subtotal || null)"
                                   x-model.number="_discountForm.val">
                        </div>
                    </label>

                    <div class="cashier-disc-preview">
                        <span class="cashier-disc-preview-label">{{ __('cashier.discount.new_total') }}</span>
                        <span class="cashier-disc-preview-amount num tnum"
                              x-text="money(Math.max(0, subtotal - (_discountForm.kind === 'pct'
                                  ? (subtotal * (parseFloat(_discountForm.val) || 0)) / 100
                                  : Math.min(parseFloat(_discountForm.val) || 0, subtotal))))"></span>
                    </div>

                    {{-- Reason + category (Slice 3) — optional, for the audit trail. --}}
                    <label class="field cashier-disc-field">
                        <span class="field-label">{{ __('cashier.discount.reason_category') }}</span>
                        {{-- x-effect: the modal resets _discountForm on each open, so push
                             the (cleared) value back into TomSelect's chip. --}}
                        <select class="pos-input"
                                x-data="enhancedSelect()"
                                x-effect="ts && ts.setValue(_discountForm.reason_category ?? '', true)"
                                x-model="_discountForm.reason_category">
                            <option value="">{{ __('cashier.discount.reason_none') }}</option>
                            @foreach (__('cashier.discount.reason_categories') as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field cashier-disc-field">
                        <span class="field-label">{{ __('cashier.discount.reason') }}</span>
                        <input type="text" class="pos-input" x-model="_discountForm.reason" maxlength="255"
                               placeholder="{{ __('cashier.discount.reason_ph') }}">
                    </label>
                </div>
                <div class="modal-foot">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            x-show="discount.type"
                            @click="removeDiscount()">
                        {{ __('cashier.discount.remove') }}
                    </button>
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="closeDiscount()">
                        {{ __('cashier.pay.cancel') }}
                    </button>
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-primary" @click="applyDiscount()">
                        <x-icon name="check" class="w-4 h-4" />
                        {{ __('cashier.discount.apply') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Per-line discount modal (Slice 4) ─────────────────── --}}
        <div class="scrim overlay-host"
             x-show="lineDiscOpen" x-cloak
             @click.self="closeLineDiscount()"
             @keydown.escape.window="if (lineDiscOpen) closeLineDiscount()">
            <div class="modal-card cashier-disc-card" role="dialog" aria-modal="true">
                <div class="modal-body">
                    <div class="cashier-disc-head">
                        <div class="text-base font-semibold">
                            {{ __('cashier.line_discount.title') }}
                            <span class="fg-tertiary" x-text="_lineDiscTarget?.name ? '· ' + _lineDiscTarget.name : ''"></span>
                        </div>
                        <button type="button" class="cashier-icon-btn" @click="closeLineDiscount()" aria-label="{{ __('cashier.pay.cancel') }}">
                            <x-icon name="x" class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="cashier-seg cashier-disc-seg" role="tablist">
                        <button type="button" :class="{ 'is-active': _lineDiscForm.kind === 'pct' }" @click="_lineDiscForm.kind = 'pct'">
                            {{ __('cashier.discount.percent') }}
                        </button>
                        <button type="button" :class="{ 'is-active': _lineDiscForm.kind === 'amt' }" @click="_lineDiscForm.kind = 'amt'">
                            {{ __('cashier.discount.amount') }}
                        </button>
                    </div>

                    <label class="field cashier-disc-field">
                        <span class="field-label"
                              x-text="_lineDiscForm.kind === 'pct' ? @js(__('cashier.discount.percent_off')) : @js(__('cashier.discount.amount_off'))"></span>
                        <div class="cashier-disc-input-wrap">
                            <span class="cashier-disc-input-prefix"
                                  x-text="_lineDiscForm.kind === 'pct' ? '%' : @js(app_currency()['symbol'] ?? '$')"></span>
                            <input type="number" class="pos-input cashier-disc-input tnum" step="0.01" min="0"
                                   :max="_lineDiscForm.kind === 'pct' ? 100 : null"
                                   x-model.number="_lineDiscForm.val">
                        </div>
                    </label>
                </div>
                <div class="modal-foot">
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost"
                            x-show="_lineDiscTarget?.discount?.type" @click="removeLineDiscount()">
                        {{ __('cashier.discount.remove') }}
                    </button>
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="closeLineDiscount()">
                        {{ __('cashier.pay.cancel') }}
                    </button>
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-primary" @click="applyLineDiscount()">
                        <x-icon name="check" class="w-4 h-4" />
                        {{ __('cashier.discount.apply') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Discount PIN modal — identifies who applied a discount; a
             manager's PIN only when it's above the store threshold. ── --}}
        <div class="scrim overlay-host"
             x-show="approvalOpen" x-cloak
             @click.self="closeApproval()"
             @keydown.escape.window="if (approvalOpen) closeApproval()">
            <div class="modal-card cashier-customer-add-card" role="dialog" aria-modal="true">
                <form class="modal-body" @submit.prevent="submitApproval()">
                    <div class="cashier-customer-head">
                        <div class="cashier-variant-title">
                            <div class="text-base font-semibold" x-text="approvalNeedsManager ? {{ Js::from(__('cashier.discount_approval.title')) }} : {{ Js::from(__('cashier.discount_approval.title_identify')) }}"></div>
                            {{-- `_pendingApproval` is { revert, effectivePct } — see cashier-page.js. --}}
                            <div class="text-sm fg-tertiary" x-text="(approvalNeedsManager ? {{ Js::from(__('cashier.discount_approval.sub')) }} : {{ Js::from(__('cashier.discount_approval.sub_identify')) }}).replace('__PCT__', (Number(_pendingApproval?.effectivePct) || 0).toFixed(1))"></div>
                        </div>
                        <button type="button" class="cashier-icon-btn" @click="closeApproval()" aria-label="{{ __('cashier.pay.cancel') }}">
                            <x-icon name="x" class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="form-stack mt-3" style="align-items: center;">
                        <x-cashier.pin-pad form-path="approvalForm.pin" on-complete="submitApproval()" />
                        <p x-show="approvalSubmitting" x-cloak class="text-sm fg-tertiary mt-2">{{ __('cashier.discount_approval.approving') }}</p>
                    </div>

                    <div class="modal-foot mt-4">
                        <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="closeApproval()" :disabled="approvalSubmitting">
                            {{ __('cashier.pay.cancel') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ── Hold prompt modal (name the hold) ─────────────────── --}}
        <div class="scrim overlay-host"
             x-show="holdNamePromptOpen" x-cloak
             @click.self="closeHoldPrompt()"
             @keydown.escape.window="if (holdNamePromptOpen) closeHoldPrompt()">
            <div class="modal-card cashier-customer-add-card" role="dialog" aria-modal="true">
                <div class="modal-body">
                    <div class="cashier-customer-head">
                        <div class="cashier-variant-title">
                            <div class="text-base font-semibold">{{ __('cashier.hold.prompt_title') }}</div>
                            <div class="cashier-variant-sub">{{ __('cashier.hold.prompt_sub') }}</div>
                        </div>
                        <button type="button" class="cashier-icon-btn" @click="closeHoldPrompt()" aria-label="{{ __('cashier.hold.prompt_cancel') }}">
                            <x-icon name="x" class="w-5 h-5" />
                        </button>
                    </div>
                    <label class="field">
                        <input type="text"
                               data-hold-label
                               class="pos-input"
                               x-model="holdLabel"
                               maxlength="64"
                               @keydown.enter.prevent="submitHold()"
                               placeholder="{{ __('cashier.hold.prompt_placeholder') }}">
                    </label>
                </div>
                <div class="modal-foot">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            :disabled="holdSubmitting"
                            @click="closeHoldPrompt()">
                        {{ __('cashier.hold.prompt_cancel') }}
                    </button>
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-primary"
                            :disabled="holdSubmitting"
                            @click="submitHold()">
                        <svg x-show="holdSubmitting" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                        </svg>
                        <span x-text="holdSubmitting ? @js(__('cashier.hold.prompt_saving')) : @js(__('cashier.hold.prompt_save'))"></span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Open drawer (no sale) prompt ──────────────────────── --}}
        <div class="scrim overlay-host"
             x-show="drawerNoSaleOpen" x-cloak
             @click.self="closeDrawerNoSale()"
             @keydown.escape.window="if (drawerNoSaleOpen) closeDrawerNoSale()">
            <div class="modal-card cashier-customer-add-card" role="dialog" aria-modal="true">
                <div class="modal-body">
                    <div class="cashier-customer-head">
                        <div class="cashier-variant-title">
                            <div class="text-base font-semibold">{{ __('cashier.drawer_no_sale.prompt_title') }}</div>
                            <div class="cashier-variant-sub">{{ __('cashier.drawer_no_sale.prompt_sub') }}</div>
                        </div>
                        <button type="button" class="cashier-icon-btn" @click="closeDrawerNoSale()" aria-label="{{ __('cashier.drawer_no_sale.prompt_cancel') }}">
                            <x-icon name="x" class="w-5 h-5" />
                        </button>
                    </div>
                    <label class="field">
                        <input type="text"
                               data-drawer-no-sale-reason
                               class="pos-input"
                               x-model="drawerNoSaleReason"
                               maxlength="255"
                               @keydown.enter.prevent="submitDrawerNoSale()"
                               placeholder="{{ __('cashier.drawer_no_sale.prompt_placeholder') }}">
                    </label>
                </div>
                <div class="modal-foot">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            :disabled="drawerNoSaleBusy"
                            @click="closeDrawerNoSale()">
                        {{ __('cashier.drawer_no_sale.prompt_cancel') }}
                    </button>
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-primary"
                            :disabled="drawerNoSaleBusy || !drawerNoSaleReason.trim()"
                            @click="submitDrawerNoSale()">
                        <svg x-show="drawerNoSaleBusy" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                        </svg>
                        <span x-text="drawerNoSaleBusy ? @js(__('cashier.drawer_no_sale.prompt_saving')) : @js(__('cashier.drawer_no_sale.prompt_save'))"></span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Held drawer ───────────────────────────────────────── --}}
        <div class="scrim overlay-host"
             x-show="heldDrawerOpen" x-cloak
             @click.self="closeHeldDrawer()"
             @keydown.escape.window="if (heldDrawerOpen) closeHeldDrawer()">
            <div class="modal-card cashier-held-card" role="dialog" aria-modal="true">
                <div class="modal-body">
                    <div class="cashier-customer-head">
                        <div class="cashier-variant-title">
                            <div class="text-base font-semibold">{{ __('cashier.held.drawer_title') }}</div>
                            <div class="cashier-variant-sub">{{ __('cashier.held.drawer_sub') }}</div>
                        </div>
                        <button type="button" class="cashier-icon-btn" @click="closeHeldDrawer()" aria-label="{{ __('cashier.pay.cancel') }}">
                            <x-icon name="x" class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="cashier-held-list">
                        <template x-for="held in heldList" :key="held.id">
                            <div class="cashier-held-row">
                                <div class="cashier-held-row-meta">
                                    <div class="cashier-held-row-label" x-text="held.label || @js(__('cashier.held.no_label'))"></div>
                                    <div class="cashier-held-row-sub mono" x-text="held.number"></div>
                                    <div class="cashier-held-row-stat">
                                        <span x-text="held.item_count"></span>
                                        <span class="ms-1 fg-tertiary">{{ __('cashier.held.items') }}</span>
                                        <span class="mx-2 fg-tertiary">·</span>
                                        <span class="num tnum" x-text="money(held.grand_total)"></span>
                                        <template x-if="held.customer">
                                            <span class="ms-2 fg-tertiary">· <span x-text="held.customer"></span></span>
                                        </template>
                                    </div>
                                </div>
                                <div class="cashier-held-row-actions">
                                    <button type="button"
                                            class="pos-btn pos-btn-sm pos-btn-ghost"
                                            :disabled="heldVoidingId === held.id"
                                            @click="voidHeld(held)">
                                        <x-icon name="trash" class="w-4 h-4" />
                                        {{ __('cashier.held.delete') }}
                                    </button>
                                    <button type="button"
                                            class="pos-btn pos-btn-sm pos-btn-primary"
                                            @click="resumeHeld(held)">
                                        <x-icon name="back" class="w-4 h-4" />
                                        {{ __('cashier.held.resume') }}
                                    </button>
                                </div>
                            </div>
                        </template>
                        <div class="cashier-customer-empty" x-show="!heldLoading && heldList.length === 0" x-cloak>
                            {{ __('cashier.held.empty') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Recent sales drawer ─────────────────────────────── --}}
        <div class="scrim overlay-host"
             x-show="recentsDrawerOpen" x-cloak
             @click.self="closeRecentsDrawer()"
             @keydown.escape.window="if (recentsDrawerOpen) closeRecentsDrawer()">
            <div class="modal-card cashier-held-card" role="dialog" aria-modal="true">
                <div class="modal-body">
                    <div class="cashier-customer-head">
                        <div class="cashier-variant-title">
                            <div class="text-base font-semibold">{{ __('cashier.recents.drawer_title') }}</div>
                            <div class="cashier-variant-sub">{{ __('cashier.recents.drawer_sub') }}</div>
                        </div>
                        <button type="button" class="cashier-icon-btn" @click="closeRecentsDrawer()" aria-label="{{ __('cashier.pay.cancel') }}">
                            <x-icon name="x" class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="cashier-held-list">
                        <template x-for="sale in recentsList" :key="sale.id">
                            <div class="cashier-held-row">
                                <div class="cashier-held-row-meta">
                                    <div class="cashier-held-row-label mono" x-text="sale.number"></div>
                                    <div class="cashier-held-row-sub" x-text="_fmtRecentDate(sale.sale_datetime)"></div>
                                    <div class="cashier-held-row-stat">
                                        <span class="num tnum" x-text="money(sale.grand_total)"></span>
                                        <template x-if="sale.customer">
                                            <span class="ms-2 fg-tertiary">· <span x-text="sale.customer"></span></span>
                                        </template>
                                    </div>
                                </div>
                                <div class="cashier-held-row-actions">
                                    <button type="button"
                                            class="pos-btn pos-btn-sm pos-btn-primary"
                                            @click="viewRecentReceipt(sale)">
                                        <x-icon name="receipt" class="w-4 h-4" />
                                        {{ __('cashier.recents.view_receipt') }}
                                    </button>
                                </div>
                            </div>
                        </template>
                        <div class="cashier-customer-empty" x-show="recentsLoading" x-cloak>
                            {{ __('cashier.recents.loading') }}
                        </div>
                        <div class="cashier-customer-empty" x-show="!recentsLoading && recentsList.length === 0" x-cloak>
                            {{ __('cashier.recents.empty') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Keyboard shortcuts help ─────────────────────────── --}}
        <div class="scrim overlay-host"
             x-show="shortcutsHelpOpen" x-cloak
             @click.self="closeShortcutsHelp()"
             @keydown.escape.window="if (shortcutsHelpOpen) closeShortcutsHelp()">
            <div class="modal-card cashier-variant-card" role="dialog" aria-modal="true">
                <div class="modal-body">
                    <div class="cashier-customer-head">
                        <div class="text-base font-semibold">{{ __('cashier.shortcuts.title') }}</div>
                        <button type="button" class="cashier-icon-btn" @click="closeShortcutsHelp()" aria-label="{{ __('cashier.pay.cancel') }}">
                            <x-icon name="x" class="w-5 h-5" />
                        </button>
                    </div>
                    <ul class="cashier-shortcut-list">
                        <li><span>{{ __('cashier.shortcuts.search') }}</span><span class="cashier-kbd">/</span></li>
                        <li><span>{{ __('Calculator') }}</span><span class="cashier-kbd">Alt+C</span></li>
                        <li><span>{{ __('cashier.shortcuts.customer') }}</span><span class="cashier-kbd">F2</span></li>
                        <li><span>{{ __('cashier.shortcuts.discount') }}</span><span class="cashier-kbd">F3</span></li>
                        <li><span>{{ __('cashier.shortcuts.hold') }}</span><span class="cashier-kbd">F4</span></li>
                        <li><span>{{ __('cashier.shortcuts.scan') }}</span><span class="cashier-kbd">F8</span></li>
                        <li><span>{{ __('cashier.shortcuts.checkout') }}</span><span class="cashier-kbd">F9</span></li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- ── Refund lookup modal ────────────────────────────────
             Operator opens this from the overflow menu, types a sale
             number (or scans the receipt's barcode into the search
             box), picks the matching sale, lands in the refund modal.
             When empty, shows the 20 most recent completed sales in
             the current store so a refund-at-the-counter is two taps. --}}
        <div class="scrim overlay-host"
             x-show="refundLookupOpen" x-cloak
             @click.self="closeRefundLookup()"
             @keydown.escape.window="if (refundLookupOpen) closeRefundLookup()">
            <div class="modal-card cashier-cust-picker-card" role="dialog" aria-modal="true">
                <div class="modal-body">
                    <div class="cashier-customer-head">
                        <div class="cashier-variant-title">
                            <div class="text-base font-semibold">{{ __('cashier.refund.lookup_title') }}</div>
                            <div class="cashier-variant-sub">{{ __('cashier.refund.lookup_sub') }}</div>
                        </div>
                        <button type="button" class="cashier-icon-btn" @click="closeRefundLookup()" aria-label="{{ __('cashier.pay.cancel') }}">
                            <x-icon name="x" class="w-5 h-5" />
                        </button>
                    </div>
                    <label class="field">
                        <input type="text"
                               data-refund-lookup-input
                               class="pos-input"
                               x-model="refundLookupQuery"
                               @input="_refundLookupDebounced()"
                               autocomplete="off"
                               spellcheck="false"
                               placeholder="{{ __('cashier.refund.lookup_placeholder') }}">
                    </label>

                    <div class="cashier-refund-results">
                        <template x-for="s in refundLookupResults" :key="s.id">
                            <button type="button"
                                    class="cashier-refund-result"
                                    @click="pickRefundSale(s)">
                                <div class="cashier-refund-result-meta">
                                    <div class="cashier-refund-result-num mono" x-text="s.number"></div>
                                    <div class="cashier-refund-result-sub">
                                        <span x-text="s.customer || @js(__('cashier.customer.walk_in'))"></span>
                                        <template x-if="s.sale_datetime">
                                            <span class="ms-2 fg-tertiary" x-text="_formatDate(s.sale_datetime)"></span>
                                        </template>
                                        <template x-if="s.status === 'partially_refunded'">
                                            <span class="ms-2 cashier-refund-partial-tag">{{ __('cashier.refund.partial_tag') }}</span>
                                        </template>
                                    </div>
                                </div>
                                <div class="cashier-refund-result-total num tnum" x-text="money(s.grand_total)"></div>
                            </button>
                        </template>

                        <div class="cashier-customer-empty"
                             x-show="!refundLookupLoading && refundLookupResults.length === 0" x-cloak>
                            {{ __('cashier.refund.lookup_empty') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Refund modal ──────────────────────────────────────
             Two-pane: left = sale lines with per-line qty + restock,
             right = reason / refund-to / notes + totals. Defaults to
             a one-click full refund (all remaining qty, restock=on,
             original-method as refund-to). The cashier clamps qty per
             line if they're only refunding part. --}}
        <div class="scrim overlay-host"
             x-show="refundOpen" x-cloak
             @click.self="closeRefund()"
             @keydown.escape.window="if (refundOpen) closeRefund()">
            <div class="modal-card cashier-refund-card" role="dialog" aria-modal="true">
                <div class="modal-body">
                    <div class="cashier-customer-head">
                        <div class="cashier-variant-title">
                            <div class="text-base font-semibold"
                                 x-text="@js(__('cashier.refund.title')) + (refundSale ? ' — ' + refundSale.number : '')"></div>
                            <div class="cashier-variant-sub">{{ __('cashier.refund.sub') }}</div>
                        </div>
                        <button type="button" class="cashier-icon-btn" @click="closeRefund()" aria-label="{{ __('cashier.pay.cancel') }}">
                            <x-icon name="x" class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="cashier-refund-grid">
                        {{-- LEFT: line picker --}}
                        <div class="cashier-refund-lines">
                            <div class="cashier-refund-lines-head">
                                <span class="cashier-refund-col-name">{{ __('cashier.refund.col_item') }}</span>
                                <span class="cashier-refund-col-qty num">{{ __('cashier.refund.col_qty') }}</span>
                                <span class="cashier-refund-col-price num">{{ __('cashier.refund.col_unit_price') }}</span>
                                <span class="cashier-refund-col-total num">{{ __('cashier.refund.col_line_total') }}</span>
                            </div>
                            <template x-for="(r, idx) in refundRows" :key="r.id">
                                <div class="cashier-refund-line">
                                    <div class="cashier-refund-line-meta">
                                        <div class="cashier-refund-line-name" x-text="r.name"></div>
                                        <div class="cashier-refund-line-sub mono">
                                            <span x-text="r.sku"></span>
                                            <template x-if="r.variant">
                                                <span> · <span x-text="r.variant"></span></span>
                                            </template>
                                            <span class="fg-tertiary ms-2"
                                                  x-text="@js(__('cashier.refund.remaining_n', ['n' => '__N__'])).replace('__N__', _fmtQty(r.remaining))"></span>
                                        </div>
                                    </div>
                                    <div class="cashier-refund-line-qty">
                                        <div class="cashier-line-qty">
                                            <button type="button" class="cashier-step" @click="refundDecrement(r)" aria-label="−">
                                                <x-icon name="minus" class="w-3 h-3" />
                                            </button>
                                            <input type="number"
                                                   class="cashier-qty-value cashier-qty-input num tnum"
                                                   :value="r.refundQty"
                                                   @change="setRefundQty(r, $event.target.value); $event.target.value = r.refundQty"
                                                   step="any"
                                                   min="0"
                                                   :max="r.remaining"
                                                   aria-label="Refund quantity">
                                            <button type="button" class="cashier-step" @click="refundIncrement(r)" aria-label="+">
                                                <x-icon name="plus" class="w-3 h-3" />
                                            </button>
                                        </div>
                                    </div>
                                    <div class="cashier-refund-line-price num tnum" x-text="money(r.unit_price)"></div>
                                    <div class="cashier-refund-line-total num tnum"
                                         x-text="money(refundLineTotal(r))"></div>
                                </div>
                            </template>
                        </div>

                        {{-- RIGHT: reason + method + notes + totals --}}
                        <div class="cashier-refund-side">
                            <label class="field">
                                <span class="field-label is-required">{{ __('cashier.refund.reason') }}</span>
                                {{-- Options come from x-for, so re-sync TomSelect whenever the
                                     list (or the selected id) changes. --}}
                                <select class="pos-input"
                                        x-data="enhancedSelect()"
                                        x-effect="refundReasons.length, syncOptions(refundReasonId)"
                                        x-model.number="refundReasonId">
                                    <option value="">{{ __('cashier.refund.reason_placeholder') }}</option>
                                    <template x-for="r in refundReasons" :key="r.id">
                                        <option :value="r.id" x-text="r.name"></option>
                                    </template>
                                </select>
                            </label>

                            <label class="field">
                                <span class="field-label">{{ __('cashier.refund.method') }}</span>
                                <select class="pos-input"
                                        x-data="enhancedSelect()"
                                        x-effect="refundMethods.length, syncOptions(refundMethodId)"
                                        x-model.number="refundMethodId">
                                    <option value="">{{ __('cashier.refund.method_placeholder') }}</option>
                                    <template x-for="m in refundMethods" :key="m.id">
                                        <option :value="m.id" x-text="m.name"></option>
                                    </template>
                                </select>
                            </label>

                            <label class="field-toggle">
                                <input type="checkbox" x-model="refundRestock">
                                <span>{{ __('cashier.refund.restock') }}</span>
                            </label>

                            <label class="field">
                                <span class="field-label">{{ __('cashier.refund.notes') }}</span>
                                <textarea class="pos-input" rows="2" maxlength="2000"
                                          x-model="refundNotes"
                                          placeholder="{{ __('cashier.refund.notes_placeholder') }}"></textarea>
                            </label>

                            <div class="cashier-refund-totals">
                                <div class="cashier-refund-totals-row">
                                    <span class="fg-tertiary">{{ __('cashier.refund.totals_subtotal') }}</span>
                                    <span class="num tnum" x-text="money(refundTotals.subtotal)"></span>
                                </div>
                                <div class="cashier-refund-totals-row">
                                    <span class="fg-tertiary">{{ __('cashier.refund.totals_tax') }}</span>
                                    <span class="num tnum" x-text="money(refundTotals.tax)"></span>
                                </div>
                                <div class="cashier-refund-totals-row cashier-refund-totals-grand">
                                    <span class="font-semibold">{{ __('cashier.refund.totals_grand') }}</span>
                                    <span class="num tnum font-semibold" x-text="money(refundTotals.grand)"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-foot">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            :disabled="refundSubmitting"
                            @click="closeRefund()">
                        {{ __('cashier.pay.cancel') }}
                    </button>
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-primary"
                            :disabled="!canSubmitRefund || refundSubmitting"
                            @click="submitRefund()">
                        <svg x-show="refundSubmitting" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                        </svg>
                        <x-icon name="check" class="w-4 h-4" x-show="!refundSubmitting" x-cloak />
                        <span x-text="refundSubmitting ? @js(__('cashier.refund.submitting')) : @js(__('cashier.refund.submit'))"></span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Refund success overlay ────────────────────────────── --}}
        <div class="scrim overlay-host cashier-success-scrim"
             x-show="refundSuccessOpen" x-cloak
             @click.self="refundSuccessOpen = false"
             @keydown.escape.window="if (refundSuccessOpen) refundSuccessOpen = false">
            <div class="modal-card cashier-success-card" role="alertdialog" aria-modal="true">
                <div class="cashier-success-icon">
                    <x-icon name="check" class="w-10 h-10" />
                </div>
                <div class="cashier-success-title">{{ __('cashier.refund.success_title') }}</div>
                <div class="cashier-success-number mono" x-text="refundSuccess?.number ?? ''"></div>
                <div class="cashier-success-amount num tnum" x-text="money(refundSuccess?.grand_total ?? 0)"></div>
                <div class="cashier-success-change">
                    {{ __('cashier.refund.success_sale_prefix') }}
                    <span class="mono" x-text="refundSuccess?.sale_number ?? ''"></span>
                </div>
                <div class="cashier-success-actions">
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-primary"
                            @click="refundSuccessOpen = false">
                        {{ __('cashier.refund.success_done') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Blind refund (scan, no invoice) ─────────────────────
             No sale to look up — the cashier scans each item straight
             into this panel's own list. Always ends at the manager-PIN
             modal below, since there's no permission that lets a
             cashier skip approval on a no-invoice refund. --}}
        <div class="scrim overlay-host"
             x-show="blindRefundOpen" x-cloak
             @click.self="closeBlindRefund()"
             @keydown.escape.window="if (blindRefundOpen) closeBlindRefund()">
            <div class="modal-card cashier-refund-card" role="dialog" aria-modal="true">
                <div class="modal-body">
                    <div class="cashier-customer-head">
                        <div class="cashier-variant-title">
                            <div class="text-base font-semibold">{{ __('cashier.blind_refund.title') }}</div>
                            <div class="cashier-variant-sub">{{ __('cashier.blind_refund.sub') }}</div>
                        </div>
                        <button type="button" class="cashier-icon-btn" @click="closeBlindRefund()" aria-label="{{ __('cashier.pay.cancel') }}">
                            <x-icon name="x" class="w-5 h-5" />
                        </button>
                    </div>

                    <input type="text"
                           class="pos-input mono mt-3"
                           x-model="blindScanQuery"
                           @keydown.enter.prevent="blindScanEnter()"
                           autofocus
                           autocomplete="off"
                           placeholder="{{ __('cashier.blind_refund.scan_placeholder') }}">

                    <div class="cashier-refund-grid">
                        <div class="cashier-refund-lines">
                            <template x-if="blindRefundRows.length === 0">
                                <div class="cashier-refund-lines-empty">{{ __('cashier.blind_refund.empty') }}</div>
                            </template>
                            <template x-for="r in blindRefundRows" :key="r.code">
                                <div class="cashier-refund-line cashier-refund-line-blind">
                                    <div class="cashier-refund-line-meta">
                                        <div class="cashier-refund-line-name" x-text="r.name"></div>
                                        <div class="cashier-refund-line-sub mono" x-text="r.sku"></div>
                                    </div>
                                    <div class="cashier-refund-line-qty">
                                        <div class="cashier-line-qty">
                                            <button type="button" class="cashier-step" @click="blindRefundDec(r)" aria-label="−">
                                                <x-icon name="minus" class="w-3 h-3" />
                                            </button>
                                            <input type="number"
                                                   class="cashier-qty-value cashier-qty-input num tnum"
                                                   x-model.number="r.qty"
                                                   step="any" min="0"
                                                   aria-label="Refund quantity">
                                            <button type="button" class="cashier-step" @click="blindRefundInc(r)" aria-label="+">
                                                <x-icon name="plus" class="w-3 h-3" />
                                            </button>
                                        </div>
                                    </div>
                                    <input type="number"
                                           class="cashier-refund-line-price-input num tnum"
                                           x-model.number="r.unit_price"
                                           step="any" min="0"
                                           aria-label="{{ __('cashier.blind_refund.col_unit_price') }}">
                                    <div class="cashier-refund-line-total num tnum"
                                         x-text="money(blindRefundLineTotal(r))"></div>
                                    <button type="button" class="cashier-icon-btn cashier-refund-line-remove" @click="blindRefundRemove(r)" aria-label="{{ __('cashier.blind_refund.col_item') }}">
                                        <x-icon name="trash" class="w-4 h-4" />
                                    </button>
                                </div>
                            </template>
                        </div>

                        <div class="cashier-refund-side">
                            <label class="field">
                                <span class="field-label is-required">{{ __('cashier.blind_refund.reason') }}</span>
                                <select class="pos-input"
                                        x-data="enhancedSelect()"
                                        x-effect="returnReasons.length, syncOptions(blindRefundReasonId)"
                                        x-model.number="blindRefundReasonId">
                                    <option value="">{{ __('cashier.blind_refund.reason_placeholder') }}</option>
                                    <template x-for="r in returnReasons" :key="r.id">
                                        <option :value="r.id" x-text="r.name"></option>
                                    </template>
                                </select>
                            </label>

                            <label class="field">
                                <span class="field-label">{{ __('cashier.blind_refund.method') }}</span>
                                <select class="pos-input"
                                        x-data="enhancedSelect()"
                                        x-effect="blindRefundNonGatewayMethods.length, syncOptions(blindRefundMethodId)"
                                        x-model.number="blindRefundMethodId">
                                    <option value="">{{ __('cashier.blind_refund.method_placeholder') }}</option>
                                    <template x-for="m in blindRefundNonGatewayMethods" :key="m.id">
                                        <option :value="m.id" x-text="m.name"></option>
                                    </template>
                                </select>
                                <p class="field-help">{{ __('cashier.blind_refund.method_gateway_hint') }}</p>
                            </label>

                            <label class="field-toggle">
                                <input type="checkbox" x-model="blindRefundRestock">
                                <span>{{ __('cashier.blind_refund.restock') }}</span>
                            </label>

                            <label class="field">
                                <span class="field-label">{{ __('cashier.blind_refund.notes') }}</span>
                                <textarea class="pos-input" rows="2" maxlength="2000"
                                          x-model="blindRefundNotes"
                                          placeholder="{{ __('cashier.blind_refund.notes_placeholder') }}"></textarea>
                            </label>

                            <div class="cashier-refund-totals">
                                <div class="cashier-refund-totals-row cashier-refund-totals-grand">
                                    <span class="font-semibold">{{ __('cashier.blind_refund.totals_grand') }}</span>
                                    <span class="num tnum font-semibold" x-text="money(blindRefundTotal)"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-foot">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            :disabled="blindRefundSubmitting"
                            @click="closeBlindRefund()">
                        {{ __('cashier.pay.cancel') }}
                    </button>
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-primary"
                            :disabled="!canSubmitBlindRefund || blindRefundSubmitting"
                            @click="submitBlindRefundApproval()">
                        <svg x-show="blindRefundSubmitting" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                        </svg>
                        <x-icon name="check" class="w-4 h-4" x-show="!blindRefundSubmitting" x-cloak />
                        <span x-text="blindRefundSubmitting ? @js(__('cashier.blind_refund.submitting')) : @js(__('cashier.blind_refund.submit'))"></span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Blind refund success overlay ────────────────────────── --}}
        <div class="scrim overlay-host cashier-success-scrim"
             x-show="blindRefundSuccessOpen" x-cloak
             @click.self="blindRefundSuccessOpen = false"
             @keydown.escape.window="if (blindRefundSuccessOpen) blindRefundSuccessOpen = false">
            <div class="modal-card cashier-success-card" role="alertdialog" aria-modal="true">
                <div class="cashier-success-icon">
                    <x-icon name="check" class="w-10 h-10" />
                </div>
                <div class="cashier-success-title">{{ __('cashier.blind_refund.success_title') }}</div>
                <div class="cashier-success-number mono" x-text="blindRefundSuccess?.number ?? ''"></div>
                <div class="cashier-success-amount num tnum" x-text="money(blindRefundSuccess?.grand_total ?? 0)"></div>
                <div class="cashier-success-actions">
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-primary"
                            @click="blindRefundSuccessOpen = false">
                        {{ __('cashier.blind_refund.success_done') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Manager PIN approval for a refund (invoice-based or blind) ── --}}
        <div class="scrim overlay-host"
             x-show="refundApprovalOpen" x-cloak
             @click.self="closeRefundApproval()"
             @keydown.escape.window="if (refundApprovalOpen) closeRefundApproval()">
            <div class="modal-card cashier-customer-add-card" role="dialog" aria-modal="true">
                <div class="modal-body">
                    <div class="cashier-customer-head">
                        <div class="cashier-variant-title">
                            <div class="text-base font-semibold">{{ __('cashier.refund_approval.title') }}</div>
                            <div class="text-sm fg-tertiary">
                                {{ __('cashier.refund_approval.sub') }}
                                <span class="num tnum font-semibold" x-text="money(_pendingRefundApproval?.amount ?? 0)"></span>
                            </div>
                        </div>
                        <button type="button" class="cashier-icon-btn" @click="closeRefundApproval()" aria-label="{{ __('cashier.pay.cancel') }}">
                            <x-icon name="x" class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="form-stack mt-3" style="align-items: center;">
                        <x-cashier.pin-pad form-path="refundApprovalForm.pin" on-complete="submitRefundApproval()" />
                        <p x-show="refundApprovalSubmitting" x-cloak class="text-sm fg-tertiary mt-2">{{ __('cashier.refund_approval.approving') }}</p>
                    </div>

                    <div class="modal-foot mt-4">
                        <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="closeRefundApproval()" :disabled="refundApprovalSubmitting">
                            {{ __('cashier.pay.cancel') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Check price (Focus layout's action rail) — scan/type a
             barcode, see what it rings up at, add nothing to the cart. --}}
        <div class="scrim overlay-host"
             x-show="priceCheckOpen" x-cloak
             @click.self="closePriceCheck()"
             @keydown.escape.window="if (priceCheckOpen) closePriceCheck()">
            <div class="modal-card cashier-customer-add-card" role="dialog" aria-modal="true">
                <div class="modal-body">
                    <div class="cashier-customer-head">
                        <div class="cashier-variant-title">
                            <div class="text-base font-semibold">{{ __('cashier.action_rail.check_price') }}</div>
                        </div>
                        <button type="button" class="cashier-icon-btn" @click="closePriceCheck()" aria-label="{{ __('cashier.pay.cancel') }}">
                            <x-icon name="x" class="w-5 h-5" />
                        </button>
                    </div>

                    <input type="text"
                           class="pos-input mono mt-3"
                           x-model="priceCheckQuery"
                           @keydown.enter.prevent="priceCheckEnter()"
                           autofocus
                           autocomplete="off"
                           placeholder="{{ __('cashier.blind_refund.scan_placeholder') }}">

                    <div class="mt-4" x-show="priceCheckResult && priceCheckResult !== 'not_found'" x-cloak>
                        <div class="text-base font-semibold" x-text="priceCheckResult?.name"></div>
                        <div class="cashier-variant-sub mono" x-text="priceCheckResult?.sku"></div>
                        <div class="mt-2 flex items-baseline gap-2">
                            <template x-if="priceCheckResult?.sale_price">
                                <span class="cashier-tile-mrp num tnum" x-text="money(priceCheckResult?.selling_price)"></span>
                            </template>
                            <span class="text-2xl font-bold num tnum" x-text="money(priceCheckResult?.charge_price)"></span>
                        </div>
                    </div>
                    <div class="mt-4 fg-tertiary" x-show="priceCheckResult === 'not_found'" x-cloak>
                        {{ __('cashier.price_check.not_found') }}
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Success overlay ─────────────────────────────────────
             `@click.self` so a click on the inner card (or its buttons)
             doesn't bubble up and dismiss the overlay before the cashier
             can decide whether to view/print the receipt. Escape still
             closes; the "New sale" button explicitly closes too. --}}
        <div class="scrim overlay-host cashier-success-scrim"
             x-show="successOpen" x-cloak
             @click.self="successOpen = false"
             @keydown.escape.window="if (successOpen) successOpen = false">
            <div class="modal-card cashier-success-card" role="alertdialog" aria-modal="true">
                <div class="cashier-success-icon">
                    <x-icon name="check" class="w-10 h-10" />
                </div>
                <div class="cashier-success-title">{{ __('cashier.success.title') }}</div>
                <div class="cashier-success-number mono" x-text="successSale?.number ?? ''"></div>
                <div class="cashier-success-amount num tnum" x-text="money(successSale?.grand_total ?? 0)"></div>
                <div class="cashier-success-change" x-show="(parseFloat(successSale?.change_returned) || 0) > 0" x-cloak>
                    {{ __('cashier.success.change') }}
                    <span class="num tnum" x-text="money(successSale?.change_returned ?? 0)"></span>
                </div>
                <div class="cashier-success-actions">
                    {{-- "Print receipt" drives the hardware print bridge
                         (WebUSB thermal → browser-print fallback), parking
                         a failed print in the queue. "View receipt" opens
                         the receipt page without auto-printing so the
                         cashier can show it on screen. Both need a synced
                         sale id, so they're shown only for an ONLINE sale. --}}
                    <a :href="receiptUrl()" target="_blank" class="pos-btn pos-btn-sm pos-btn-ghost"
                       x-show="successSale?.id" x-cloak>
                        <x-icon name="receipt" class="w-4 h-4" />
                        {{ __('cashier.success.view') }}
                    </a>
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost"
                            x-show="successSale?.id" x-cloak
                            :disabled="printingReceipt"
                            @click="printSaleReceipt()">
                        <svg x-show="printingReceipt" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                        </svg>
                        <template x-if="!printingReceipt"><x-icon name="printer" class="w-4 h-4" /></template>
                        {{ __('cashier.success.print') }}
                    </button>

                    {{-- OFFLINE counterparts. The receipt is composed
                         client-side from the cart snapshot (offline-sync
                         doc §13) — no server id needed — so the cashier can
                         still hand the customer a paper receipt at the
                         counter. Shown only when there's a snapshot to
                         render. --}}
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost"
                            x-show="successSale?.offline && successSale?.receipt" x-cloak
                            @click="viewOfflineReceipt()">
                        <x-icon name="receipt" class="w-4 h-4" />
                        {{ __('cashier.success.view') }}
                    </button>
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost"
                            x-show="successSale?.offline && successSale?.receipt" x-cloak
                            :disabled="printingReceipt"
                            @click="printOfflineReceipt()">
                        <svg x-show="printingReceipt" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                        </svg>
                        <template x-if="!printingReceipt"><x-icon name="printer" class="w-4 h-4" /></template>
                        {{ __('cashier.success.print') }}
                    </button>
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-primary"
                            @click="successOpen = false">
                        {{ __('cashier.success.new') }}
                    </button>
                </div>
            </div>
        </div>
        {{-- ── Calculator modal ────────────────────────────────────────── --}}
        <div class="scrim overlay-host"
             x-data="calculator()"
             @open-calculator.window="open = true"
             x-show="open" x-cloak
             @click.self="open = false"
             @keydown.window="handleKey($event)"
             @keydown.escape.window="if (open) open = false">

            <div class="modal-card calc-card" role="dialog" aria-modal="true">
                <div class="calc-head">
                    <h3 class="calc-title">{{ __('Calculator') }}</h3>
                    <button type="button" class="cashier-icon-btn calc-close" @click="open = false" aria-label="{{ __('Close') }}">
                        <x-icon name="x" class="w-4 h-4"/>
                    </button>
                </div>

                <div class="calc-screen">
                    <div class="calc-expr" x-text="liveExpr" x-show="liveExpr" x-cloak></div>
                    <div class="calc-display" x-text="display"></div>
                </div>

                <div class="calc-grid">
                    <button type="button" class="calc-key calc-key-op" @click="press('C')">C</button>
                    <button type="button" class="calc-key calc-key-op" @click="press('CE')">CE</button>
                    <button type="button" class="calc-key calc-key-op" @click="press('/')">&divide;</button>
                    <button type="button" class="calc-key calc-key-op" @click="press('*')">&times;</button>

                    <button type="button" class="calc-key" @click="press(7)">7</button>
                    <button type="button" class="calc-key" @click="press(8)">8</button>
                    <button type="button" class="calc-key" @click="press(9)">9</button>
                    <button type="button" class="calc-key calc-key-op" @click="press('-')">&minus;</button>

                    <button type="button" class="calc-key" @click="press(4)">4</button>
                    <button type="button" class="calc-key" @click="press(5)">5</button>
                    <button type="button" class="calc-key" @click="press(6)">6</button>
                    <button type="button" class="calc-key calc-key-op" @click="press('+')">+</button>

                    <button type="button" class="calc-key" @click="press(1)">1</button>
                    <button type="button" class="calc-key" @click="press(2)">2</button>
                    <button type="button" class="calc-key" @click="press(3)">3</button>
                    <button type="button" class="calc-key calc-key-eq" @click="press('=')">=</button>

                    <button type="button" class="calc-key calc-key-zero" @click="press(0)">0</button>
                    <button type="button" class="calc-key" @click="press('.')">.</button>
                </div>

                {{-- History — most recent first; tap a row to reuse its result. --}}
                <div class="calc-history">
                    <div class="calc-history-head">
                        <span class="calc-history-title">{{ __('History') }}</span>
                        <button type="button" class="calc-history-clear" @click="clearHistory()" x-show="history.length" x-cloak>
                            {{ __('Clear') }}
                        </button>
                    </div>
                    <div class="calc-history-list">
                        <template x-for="(h, i) in history" :key="i">
                            <button type="button" class="calc-history-item" @click="useHistory(h)" :title="@js(__('Use this result'))">
                                <span class="calc-history-expr" x-text="h.expr"></span>
                            </button>
                        </template>
                        <div class="calc-history-empty" x-show="!history.length" x-cloak>{{ __('No calculations yet') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Shift gate (Slices A + B, Phase 5) ────────────────────────
         Blocking overlay over the cashier. Step 1 (Slice B): pick the
         terminal this device runs, when the store has terminals and
         none is bound. Step 1.5 (Phase 5): when `require_day_open` is
         on and today's trading day isn't open yet, block here — a
         manager with `shifts.open_day` can open it inline, everyone
         else just waits (or bypasses, same permission as the shift
         step). Step 2 (Slice A): open a shift when the store enforces
         shifts. Bypass-permission holders can skip the shift.
         The denomination helper + "use yesterday's close" land in Slice C. --}}
    @if (($shiftGate['needsTerminal'] ?? false) || (($shiftGate['enforce'] ?? false) && ! ($shiftGate['hasOpen'] ?? false)) || (($shiftGate['dayRequired'] ?? false) && ! ($shiftGate['dayOpen'] ?? false)))
    <div class="scrim overlay-host cashier-shift-gate"
         x-data="shiftGate({
            openUrl:       @js($shiftGate['openUrl']),
            canBypass:     @js($shiftGate['canBypass']),
            reloadUrl:     @js(route('cashier.index')),
            needsTerminal: @js($shiftGate['needsTerminal'] ?? false),
            terminalUrl:   @js($shiftGate['terminalUrl'] ?? null),
            terminals:     @js($shiftGate['terminals'] ?? []),
            denominations: @js($shiftGate['denominations'] ?? []),
            dayRequired:   @js($shiftGate['dayRequired'] ?? false),
            dayOpen:       @js($shiftGate['dayOpen'] ?? true),
            canOpenDay:    @js($shiftGate['canOpenDay'] ?? false),
            openDayUrl:    @js($shiftGate['openDayUrl'] ?? null),
         })"
         x-show="visible" x-cloak>
        <div class="modal-card cashier-shift-gate-card" role="alertdialog" aria-modal="true">

            {{-- Step 1 — terminal selection (Slice B) --}}
            <template x-if="mode === 'terminal'">
                <div>
                    <div class="cashier-shift-gate-head">
                        <span class="cashier-shift-gate-icon"><x-icon name="pos" class="w-6 h-6" /></span>
                        <div>
                            <div class="cashier-shift-gate-title">{{ __('shifts.gate.terminal_title') }}</div>
                            <div class="cashier-shift-gate-sub">{{ __('shifts.gate.terminal_sub') }}</div>
                        </div>
                    </div>

                    <form class="form-stack" @submit.prevent="selectTerminal()">
                        <div class="field">
                            <label class="field-label" for="gate-terminal">{{ __('shifts.gate.terminal_label') }}</label>
                            {{-- `terminals` is server-rendered and never changes, so this
                                 effect fires exactly once — during the select's own directive
                                 pass, BEFORE Alpine renders the x-for beneath it. Without the
                                 $nextTick, syncOptions() reads an empty option list and the
                                 dropdown opens blank. --}}
                            <select id="gate-terminal" class="pos-input"
                                    x-data="enhancedSelect()"
                                    x-effect="terminals.length, $nextTick(() => syncOptions(terminalId))"
                                    x-model.number="terminalId" x-ref="terminalSelect">
                                <template x-for="t in terminals" :key="t.id">
                                    <option :value="t.id" x-text="t.code ? (t.name + ' (' + t.code + ')') : t.name"></option>
                                </template>
                            </select>
                        </div>

                        <div class="cashier-shift-gate-actions">
                            <button type="submit" class="pos-btn pos-btn-primary" :disabled="submitting || !terminalId">
                                <span x-show="!submitting">{{ __('shifts.gate.terminal_continue') }}</span>
                                <span x-show="submitting" x-cloak>{{ __('shifts.gate.starting') }}</span>
                            </button>
                        </div>

                        {{-- The gate covers the whole cashier screen and has no dismiss.
                             Someone who opened /cashier by mistake, or who isn't ready to
                             bind this device to a till, needs a way back out. --}}
                        <a href="{{ route('admin.dashboard') }}" class="cashier-shift-gate-back">
                            <x-icon name="back" class="w-4 h-4" />
                            {{ __('shifts.gate.leave') }}
                        </a>
                    </form>
                </div>
            </template>

            {{-- Step 1.5 — trading day must be opened first (Phase 5) --}}
            <template x-if="mode === 'day'">
                <div>
                    <div class="cashier-shift-gate-head">
                        <span class="cashier-shift-gate-icon"><x-icon name="lock" class="w-6 h-6" /></span>
                        <div>
                            <div class="cashier-shift-gate-title">{{ __('shifts.gate.day_title') }}</div>
                            <div class="cashier-shift-gate-sub">{{ __('shifts.gate.day_sub') }}</div>
                        </div>
                    </div>

                    <div class="cashier-shift-gate-actions">
                        <template x-if="canOpenDay">
                            <button type="button" class="pos-btn pos-btn-primary" :disabled="submitting" @click="openDay()">
                                <span x-show="!submitting">{{ __('shifts.gate.day_open_button') }}</span>
                                <span x-show="submitting" x-cloak>{{ __('shifts.gate.day_opening') }}</span>
                            </button>
                        </template>
                        <template x-if="canBypass">
                            <button type="button" class="pos-btn pos-btn-ghost" :disabled="submitting" @click="bypass()">
                                {{ __('shifts.gate.sell_without') }}
                            </button>
                        </template>
                    </div>

                    <a href="{{ route('admin.dashboard') }}" class="cashier-shift-gate-back">
                        <x-icon name="back" class="w-4 h-4" />
                        {{ __('shifts.gate.leave') }}
                    </a>
                </div>
            </template>

            {{-- Step 2 — open shift (Slice A) --}}
            <template x-if="mode === 'shift'">
                <div>
                    <div class="cashier-shift-gate-head">
                        <span class="cashier-shift-gate-icon"><x-icon name="lock" class="w-6 h-6" /></span>
                        <div>
                            <div class="cashier-shift-gate-title">{{ __('shifts.gate.title') }}</div>
                            <div class="cashier-shift-gate-sub">{{ __('shifts.gate.sub') }}</div>
                        </div>
                    </div>

                    <form class="form-stack" @submit.prevent="submit()">
                        <div class="field">
                            <label class="field-label" for="gate-opening-cash">{{ __('shifts.fields.opening_cash') }}</label>
                            <input id="gate-opening-cash"
                                   type="number" step="0.01" min="0" inputmode="decimal"
                                   class="pos-input num tnum"
                                   x-model="openingCash"
                                   x-ref="openingCash"
                                   placeholder="0.00">
                        </div>

                        {{-- Denomination helper (Slice C) — count the drawer
                             and push the total into the field above. --}}
                        <div class="denom-helper" x-show="denoms.length" x-cloak>
                            <button type="button" class="denom-toggle" @click="denomOpen = !denomOpen" :aria-expanded="denomOpen">
                                <x-icon name="chevron" class="w-4 h-4 denom-chevron" />
                                <span>{{ __('shifts.denom.toggle') }}</span>
                            </button>
                            <div class="denom-body" x-show="denomOpen" x-cloak>
                                <div class="denom-grid">
                                    <template x-for="d in denoms" :key="d">
                                        <div class="denom-row">
                                            <span class="denom-face tnum" x-text="$formatMoney(d)"></span>
                                            <span class="denom-x">×</span>
                                            <input type="number" min="0" step="1" inputmode="numeric"
                                                   class="pos-input denom-count num tnum"
                                                   x-model.number="denomCounts[d]"
                                                   placeholder="0">
                                            <span class="denom-sub tnum" x-text="$formatMoney(denomSubtotal(d))"></span>
                                        </div>
                                    </template>
                                </div>
                                <div class="denom-total">
                                    <span class="denom-total-label">{{ __('shifts.denom.total') }}</span>
                                    <span class="denom-total-value tnum" x-text="$formatMoney(denomTotal)"></span>
                                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="applyDenomTotal()">
                                        {{ __('shifts.denom.use_total') }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="field">
                            <label class="field-label" for="gate-notes">{{ __('shifts.fields.notes') }}</label>
                            <input id="gate-notes" type="text" maxlength="1000" class="pos-input" x-model="notes">
                        </div>

                        <div class="cashier-shift-gate-actions">
                            <button type="submit" class="pos-btn pos-btn-primary" :disabled="submitting">
                                <span x-show="!submitting">{{ __('shifts.actions.start_shift') }}</span>
                                <span x-show="submitting" x-cloak>{{ __('shifts.gate.starting') }}</span>
                            </button>
                            <template x-if="canBypass">
                                <button type="button" class="pos-btn pos-btn-ghost" :disabled="submitting" @click="bypass()">
                                    {{ __('shifts.gate.sell_without') }}
                                </button>
                            </template>
                        </div>

                        <a href="{{ route('admin.dashboard') }}" class="cashier-shift-gate-back">
                            <x-icon name="back" class="w-4 h-4" />
                            {{ __('shifts.gate.leave') }}
                        </a>
                    </form>
                </div>
            </template>
        </div>
    </div>
    @endif
</x-cashier-layout>
