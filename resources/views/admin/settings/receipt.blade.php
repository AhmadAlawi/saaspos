<x-admin-layout
    active="settings"
    :title="__('settings.receipt.title')"
    :crumbs="[
        ['label' => __('settings.crumb_parent')],
        ['label' => __('settings.title'), 'href' => route('admin.settings.index')],
        ['label' => __('settings.receipt.title')],
    ]">

    @php
        $r = [
            'paper_size'    => old('receipt_paper_size',         $company->receipt_paper_size ?: '80mm'),
            'show_logo'     => (bool) old('receipt_show_logo',          $company->receipt_show_logo),
            'show_customer' => (bool) old('receipt_show_customer',      $company->receipt_show_customer),
            'show_cashier'  => (bool) old('receipt_show_cashier',       $company->receipt_show_cashier),
            'show_tax'      => (bool) old('receipt_show_tax_breakdown', $company->receipt_show_tax_breakdown),
            'show_barcode'  => (bool) old('receipt_show_barcode',       $company->receipt_show_barcode),
            'show_qr'       => (bool) old('receipt_show_qr',            $company->receipt_show_qr),
            'show_sku'      => (bool) old('receipt_show_sku',           $company->receipt_show_sku),
            'show_hsn'      => (bool) old('receipt_show_hsn',           $company->receipt_show_hsn),
            'show_hsn_sum'  => (bool) old('receipt_show_hsn_summary',   $company->receipt_show_hsn_summary),
            'header'        => old('receipt_header',        (string) $company->receipt_header),
            'footer'        => old('receipt_footer',        (string) $company->receipt_footer),
            'return_policy' => old('receipt_return_policy', (string) $company->receipt_return_policy),
        ];
    @endphp

    <div class="page-wide"
         x-data="receiptSettings({
             paperSize:        @js($r['paper_size']),
             showLogo:         {{ $r['show_logo']     ? 'true' : 'false' }},
             showCustomer:     {{ $r['show_customer'] ? 'true' : 'false' }},
             showCashier:      {{ $r['show_cashier']  ? 'true' : 'false' }},
             showTaxBreakdown: {{ $r['show_tax']      ? 'true' : 'false' }},
             showBarcode:      {{ $r['show_barcode']  ? 'true' : 'false' }},
             showQr:           {{ $r['show_qr']       ? 'true' : 'false' }},
             showSku:          {{ $r['show_sku']      ? 'true' : 'false' }},
             showHsn:          {{ $r['show_hsn']      ? 'true' : 'false' }},
             showHsnSummary:   {{ $r['show_hsn_sum']  ? 'true' : 'false' }},
             header:           @js($r['header']),
             footer:           @js($r['footer']),
             returnPolicy:     @js($r['return_policy']),
         })">

        {{-- AJAX via lib/ajax-form.js. --}}
        <form method="POST" action="{{ route('admin.settings.receipt.update') }}" data-ajax-form>
            @csrf
            @method('PATCH')

            <div class="page-header mb-6">
                <div class="flex items-start gap-3">
                    <a href="{{ route('admin.settings.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('settings.title') }}">
                        <x-icon name="back" class="w-4 h-4" />
                    </a>
                    <div>
                        <h1 class="page-title">{{ __('settings.receipt.title') }}</h1>
                        <p class="page-sub">{{ __('settings.receipt.sub') }}</p>
                    </div>
                </div>
                <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="check" class="w-4 h-4" />
                    {{ __('settings.receipt.actions.save') }}
                </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_380px] gap-5 items-start">
                {{-- Controls --}}
                <div class="space-y-5">
                    {{-- Paper size --}}
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('settings.receipt.sections.paper') }}</div>
                            <div class="card-title-sub">{{ __('settings.receipt.sections.paper_sub') }}</div>
                        </div></div>
                        <div class="card-body">
                            <label class="field">
                                <span class="field-label is-required">{{ __('settings.receipt.fields.paper_size') }}</span>
                                <select x-data="enhancedSelect()"
                                        x-effect="ts && ts.setValue(paperSize, true)"
                                        x-model="paperSize"
                                        name="receipt_paper_size"
                                        class="pos-input">
                                    @foreach ($paperSizes as $size)
                                        <option value="{{ $size }}" @selected($r['paper_size'] === $size)>{{ __("settings.receipt.paper.$size") }}</option>
                                    @endforeach
                                </select>
                                @error('receipt_paper_size')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                        </div>
                    </div>

                    {{-- What to show --}}
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('settings.receipt.sections.show') }}</div>
                            <div class="card-title-sub">{{ __('settings.receipt.sections.show_sub') }}</div>
                        </div></div>
                        <div class="card-body">
                            <div class="form-stack">
                                @foreach ([
                                    ['name'=>'receipt_show_logo',          'model'=>'showLogo',         'key'=>'show_logo'],
                                    ['name'=>'receipt_show_customer',      'model'=>'showCustomer',     'key'=>'show_customer'],
                                    ['name'=>'receipt_show_cashier',       'model'=>'showCashier',      'key'=>'show_cashier'],
                                    ['name'=>'receipt_show_tax_breakdown', 'model'=>'showTaxBreakdown', 'key'=>'show_tax'],
                                    ['name'=>'receipt_show_barcode',       'model'=>'showBarcode',      'key'=>'show_barcode'],
                                    ['name'=>'receipt_show_qr',            'model'=>'showQr',           'key'=>'show_qr'],
                                ] as $t)
                                    <label class="field-toggle">
                                        <input type="hidden" name="{{ $t['name'] }}" value="0">
                                        <input type="checkbox" name="{{ $t['name'] }}" value="1" x-model="{{ $t['model'] }}">
                                        <span>{{ __('settings.receipt.fields.'.$t['key']) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Line item details — SKU / HSN per line + the GST
                         HSN-wise tax summary block. Off by default so retail
                         receipts stay short; GST sellers turn them on. --}}
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('settings.receipt.sections.line_items') }}</div>
                            <div class="card-title-sub">{{ __('settings.receipt.sections.line_items_sub') }}</div>
                        </div></div>
                        <div class="card-body">
                            <div class="form-stack">
                                @foreach ([
                                    ['name'=>'receipt_show_sku',         'model'=>'showSku',        'key'=>'show_sku'],
                                    ['name'=>'receipt_show_hsn',         'model'=>'showHsn',        'key'=>'show_hsn'],
                                    ['name'=>'receipt_show_hsn_summary', 'model'=>'showHsnSummary', 'key'=>'show_hsn_summary'],
                                ] as $t)
                                    <label class="field-toggle">
                                        <input type="hidden" name="{{ $t['name'] }}" value="0">
                                        <input type="checkbox" name="{{ $t['name'] }}" value="1" x-model="{{ $t['model'] }}">
                                        <span>{{ __('settings.receipt.fields.'.$t['key']) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- Receipt text --}}
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('settings.receipt.sections.content') }}</div>
                            <div class="card-title-sub">{{ __('settings.receipt.sections.content_sub') }}</div>
                        </div></div>
                        <div class="card-body">
                            <div class="form-stack">
                                <label class="field">
                                    <span class="field-label">{{ __('settings.receipt.fields.header') }}</span>
                                    <textarea name="receipt_header" x-model="header" rows="3" maxlength="500"
                                              class="pos-input">{{ $r['header'] }}</textarea>
                                    <p class="field-help">{{ __('settings.receipt.fields.header_help') }}</p>
                                    @error('receipt_header')<p class="field-error">{{ $message }}</p>@enderror
                                </label>

                                <label class="field">
                                    <span class="field-label">{{ __('settings.receipt.fields.footer') }}</span>
                                    <textarea name="receipt_footer" x-model="footer" rows="3" maxlength="500"
                                              class="pos-input">{{ $r['footer'] }}</textarea>
                                    <p class="field-help">{{ __('settings.receipt.fields.footer_help') }}</p>
                                    @error('receipt_footer')<p class="field-error">{{ $message }}</p>@enderror
                                </label>

                                <label class="field">
                                    <span class="field-label">{{ __('settings.receipt.fields.return_policy') }}</span>
                                    <textarea name="receipt_return_policy" x-model="returnPolicy" rows="2" maxlength="500"
                                              class="pos-input">{{ $r['return_policy'] }}</textarea>
                                    <p class="field-help">{{ __('settings.receipt.fields.return_help') }}</p>
                                    @error('receipt_return_policy')<p class="field-error">{{ $message }}</p>@enderror
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Live preview --}}
                <div class="space-y-5 lg:sticky lg:top-4">
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('settings.receipt.preview') }}</div>
                        </div></div>
                        <div class="card-body">
                            <x-admin.receipt-preview />
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
