@props([
    'reportKey',            // stable ReportRegistry key for the current report
    'canShare' => false,    // may the user save a report shared with the store?
])

{{-- "Save view" — captures the report's current query params (period, store,
     filters) under a name so it can be re-opened from Reports → Saved. --}}
<div x-data="{
        open: false,
        name: '',
        shared: false,
        saving: false,
        currentParams() {
            const q = new URLSearchParams(window.location.search);
            const allowed = @js(\App\Support\ReportRegistry::ALLOWED_PARAMS);
            const out = {};
            allowed.forEach((k) => { if (q.get(k)) out[k] = q.get(k); });
            return out;
        },
        async save() {
            if (this.saving) return;
            if (! this.name.trim()) {
                $store.toasts.push({ type: 'error', message: @js(__('reports.saved.errors.name_required')) });
                return;
            }
            this.saving = true;
            try {
                await $http.post(@js(route('admin.reports.saved.store')), {
                    report_key: @js($reportKey),
                    name: this.name.trim(),
                    is_shared: this.shared,
                    parameters: this.currentParams(),
                });
                $store.toasts.push({ type: 'success', message: @js(__('reports.saved.saved_toast')) });
                this.open = false;
                this.name = '';
                this.shared = false;
            } catch (e) {
                $store.toasts.push({ type: 'error', message: e?.message || @js(__('reports.saved.errors.save_failed')) });
            } finally {
                this.saving = false;
            }
        }
     }">
    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="open = true">
        <x-icon name="star" class="w-4 h-4" />
        {{ __('reports.saved.save_button') }}
    </button>

    <div class="scrim overlay-host" x-show="open" x-cloak
         @click.self="open = false"
         @keydown.escape.window="if (open) open = false">
        <div class="modal-card" role="dialog" aria-modal="true">
            <div class="modal-head">
                <div class="modal-title">{{ __('reports.saved.modal_title') }}</div>
                <button type="button" class="modal-x" @click="open = false" aria-label="{{ __('reports.saved.cancel') }}">&times;</button>
            </div>
            <div class="modal-body">
                <label class="field">
                    <span class="field-label">{{ __('reports.saved.name_label') }}</span>
                    <input type="text" x-model="name" class="pos-input" maxlength="120"
                           placeholder="{{ __('reports.saved.name_placeholder') }}"
                           @keydown.enter.prevent="save()" x-ref="nameInput"
                           x-effect="if (open) $nextTick(() => $refs.nameInput.focus())">
                </label>
                @if ($canShare)
                    <label class="flex items-center gap-2 mt-3 cursor-pointer">
                        <input type="checkbox" x-model="shared" class="accent-accent w-4 h-4">
                        <span class="text-sm">{{ __('reports.saved.share_label') }}</span>
                    </label>
                @endif
            </div>
            <div class="modal-foot">
                <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="open = false" :disabled="saving">
                    {{ __('reports.saved.cancel') }}
                </button>
                <button type="button" class="pos-btn pos-btn-sm pos-btn-primary" @click="save()" :disabled="saving">
                    <svg x-show="saving" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                    </svg>
                    {{ __('reports.saved.save_confirm') }}
                </button>
            </div>
        </div>
    </div>
</div>
