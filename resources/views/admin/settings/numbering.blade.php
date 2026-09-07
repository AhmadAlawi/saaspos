<x-admin-layout
    active="settings"
    :title="__('settings.numbering.title')"
    :crumbs="[
        ['label' => __('settings.crumb_parent')],
        ['label' => __('settings.title'), 'href' => route('admin.settings.index')],
        ['label' => __('settings.numbering.title')],
    ]">

    @php
        $v = [
            'sale_number_format' => old('sale_number_format', $company->sale_number_format ?: $defaultSale),
            'hold_number_format' => old('hold_number_format', $company->hold_number_format ?: $defaultHold),
        ];
    @endphp

    <div class="page-wide"
         x-data="{
             sale: @js($v['sale_number_format']),
             hold: @js($v['hold_number_format']),
             defaultSale: @js($defaultSale),
             defaultHold: @js($defaultHold),
             today: new Date(),

             // Best-effort client-side preview that mirrors
             // `\App\Support\NumberFormat::render` for the Y/m/d/seq
             // placeholders. Lets the admin sanity-check their format
             // string without saving.
             preview(template) {
                 const t  = this.today;
                 const Y  = String(t.getFullYear());
                 const y  = Y.slice(-2);
                 const m  = String(t.getMonth() + 1).padStart(2, '0');
                 const d  = String(t.getDate()).padStart(2, '0');
                 const seq = 42;
                 let s = String(template || '')
                     .replaceAll('{store}', @js($sampleStore->code ?: 'MAIN'))
                     .replaceAll('{Y}',  Y)
                     .replaceAll('{y}',  y)
                     .replaceAll('{m}',  m)
                     .replaceAll('{d}',  d)
                     .replaceAll('{Ym}',  Y + m)
                     .replaceAll('{Ymd}', Y + m + d)
                     .replaceAll('{seq}', String(seq));
                 s = s.replace(/\{seq:(\d+)\}/g, (_, n) => String(seq).padStart(Math.min(9, +n), '0'));
                 return s;
             },
         }">

        <form method="POST" action="{{ route('admin.settings.numbering.update') }}" data-ajax-form>
            @csrf
            @method('PATCH')

            <div class="page-header mb-6">
                <div class="flex items-start gap-3">
                    <a href="{{ route('admin.settings.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('settings.title') }}">
                        <x-icon name="back" class="w-4 h-4" />
                    </a>
                    <div>
                        <h1 class="page-title">{{ __('settings.numbering.title') }}</h1>
                        <p class="page-sub">{{ __('settings.numbering.sub') }}</p>
                    </div>
                </div>
                <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="check" class="w-4 h-4" />
                    {{ __('settings.numbering.actions.save') }}
                </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
                <div class="space-y-5">
                    {{-- Sale format --}}
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('settings.numbering.sections.sale') }}</div>
                            <div class="card-title-sub">{{ __('settings.numbering.sections.sale_sub') }}</div>
                        </div></div>
                        <div class="card-body">
                            <label class="field">
                                <span class="field-label is-required">{{ __('settings.numbering.fields.format') }}</span>
                                <input type="text"
                                       name="sale_number_format"
                                       class="pos-input mono"
                                       x-model="sale"
                                       maxlength="191"
                                       placeholder="{{ $defaultSale }}">
                                <span class="field-help">{{ __('settings.numbering.fields.format_help') }}</span>
                            </label>

                            <div class="cashier-numbering-preview" :class="{ 'is-empty': !sale.trim() }">
                                <span class="cashier-numbering-preview-label">{{ __('settings.numbering.preview') }}</span>
                                <span class="cashier-numbering-preview-value mono tnum" x-text="preview(sale.trim() || defaultSale)"></span>
                            </div>

                            <button type="button"
                                    class="pos-btn pos-btn-sm pos-btn-ghost mt-2"
                                    @click="sale = defaultSale">
                                <x-icon name="refresh" class="w-3.5 h-3.5" />
                                {{ __('settings.numbering.reset_default') }}
                            </button>
                        </div>
                    </div>

                    {{-- Hold format --}}
                    <div class="card">
                        <div class="card-header"><div>
                            <div class="card-title">{{ __('settings.numbering.sections.hold') }}</div>
                            <div class="card-title-sub">{{ __('settings.numbering.sections.hold_sub') }}</div>
                        </div></div>
                        <div class="card-body">
                            <label class="field">
                                <span class="field-label is-required">{{ __('settings.numbering.fields.format') }}</span>
                                <input type="text"
                                       name="hold_number_format"
                                       class="pos-input mono"
                                       x-model="hold"
                                       maxlength="191"
                                       placeholder="{{ $defaultHold }}">
                                <span class="field-help">{{ __('settings.numbering.fields.format_help') }}</span>
                            </label>

                            <div class="cashier-numbering-preview" :class="{ 'is-empty': !hold.trim() }">
                                <span class="cashier-numbering-preview-label">{{ __('settings.numbering.preview') }}</span>
                                <span class="cashier-numbering-preview-value mono tnum" x-text="preview(hold.trim() || defaultHold)"></span>
                            </div>

                            <button type="button"
                                    class="pos-btn pos-btn-sm pos-btn-ghost mt-2"
                                    @click="hold = defaultHold">
                                <x-icon name="refresh" class="w-3.5 h-3.5" />
                                {{ __('settings.numbering.reset_default') }}
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Placeholder reference card. Each token has a copy
                     button on the right — `navigator.clipboard.writeText`
                     puts the placeholder on the clipboard so the admin
                     doesn't have to retype `{seq:04}` etc. by hand. --}}
                <div class="card"
                     x-data="{
                         async copy(token) {
                             try {
                                 await navigator.clipboard.writeText(token);
                                 this.$store.toasts?.push({
                                     type: 'success',
                                     message: 'Copied ' + token,
                                     duration: 1500,
                                 });
                             } catch (e) {
                                 // Fallback for non-HTTPS contexts: temporarily
                                 // create a hidden input and select+execCommand.
                                 const el = document.createElement('input');
                                 el.value = token;
                                 document.body.appendChild(el);
                                 el.select();
                                 try { document.execCommand('copy'); } catch (_) {}
                                 el.remove();
                                 this.$store.toasts?.push({ type: 'success', message: 'Copied ' + token, duration: 1500 });
                             }
                         },
                     }">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('settings.numbering.sections.placeholders') }}</div>
                        <div class="card-title-sub">{{ __('settings.numbering.sections.placeholders_sub') }}</div>
                    </div></div>
                    <div class="card-body">
                        <ul class="cashier-numbering-tokens">
                            @foreach ($placeholders as $p)
                                <li class="cashier-numbering-token">
                                    <code class="mono">{{ $p['token'] }}</code>
                                    <span>{{ $p['desc'] }}</span>
                                    <button type="button"
                                            class="cashier-numbering-copy"
                                            @click="copy({{ \Illuminate\Support\Js::from($p['token']) }})"
                                            :title="@js(__('settings.numbering.copy'))"
                                            :aria-label="@js(__('settings.numbering.copy'))">
                                        <x-icon name="copy" class="w-3.5 h-3.5" />
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                        <div class="cashier-numbering-note">
                            {{ __('settings.numbering.note') }}
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
