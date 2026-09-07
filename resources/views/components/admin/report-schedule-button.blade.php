@props([
    'reportKey',   // stable ReportRegistry key for the current report
])

{{-- "Schedule" — captures the report's current params (period, store, filters)
     and sets up a recurring email delivery on a daily / weekly / monthly
     cadence. Posts to the scheduled-reports store endpoint. --}}
<div x-data="{
        open: false,
        saving: false,
        form: {
            name: '',
            frequency: 'daily',
            day_of_week: '1',
            day_of_month: '1',
            time_of_day: '08:00',
            format: 'pdf',
            recipients: '',
            subject: '',
            message: '',
        },
        currentParams() {
            const q = new URLSearchParams(window.location.search);
            const allowed = @js(\App\Support\ReportRegistry::ALLOWED_PARAMS);
            const out = {};
            allowed.forEach((k) => { if (q.get(k)) out[k] = q.get(k); });
            return out;
        },
        emails() {
            return this.form.recipients.split(/[,;\n]+/).map((s) => s.trim()).filter(Boolean);
        },
        async save() {
            if (this.saving) return;
            if (! this.form.name.trim()) {
                $store.toasts.push({ type: 'error', message: @js(__('reports.schedule.errors.name_required')) });
                return;
            }
            if (this.emails().length === 0) {
                $store.toasts.push({ type: 'error', message: @js(__('reports.schedule.errors.recipients_required')) });
                return;
            }
            this.saving = true;
            try {
                await $http.post(@js(route('admin.reports.schedules.store')), {
                    report_key: @js($reportKey),
                    name: this.form.name.trim(),
                    parameters: this.currentParams(),
                    frequency: this.form.frequency,
                    day_of_week: this.form.frequency === 'weekly' ? Number(this.form.day_of_week) : null,
                    day_of_month: this.form.frequency === 'monthly' ? Number(this.form.day_of_month) : null,
                    time_of_day: this.form.time_of_day,
                    format: this.form.format,
                    recipients_email: this.emails(),
                    subject_template: this.form.subject.trim() || null,
                    message_template: this.form.message.trim() || null,
                });
                $store.toasts.push({ type: 'success', message: @js(__('reports.schedule.saved_toast')) });
                this.open = false;
            } catch (e) {
                $store.toasts.push({ type: 'error', message: e?.message || @js(__('reports.schedule.errors.save_failed')) });
            } finally {
                this.saving = false;
            }
        }
     }">
    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="open = true">
        <x-icon name="clock" class="w-4 h-4" />
        {{ __('reports.schedule.schedule_button') }}
    </button>

    <div class="scrim overlay-host" x-show="open" x-cloak
         @click.self="open = false"
         @keydown.escape.window="if (open) open = false">
        <div class="modal-card" role="dialog" aria-modal="true">
            <div class="modal-head">
                <div class="modal-title">{{ __('reports.schedule.modal_title') }}</div>
                <button type="button" class="modal-x" @click="open = false" aria-label="{{ __('reports.schedule.cancel') }}">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-stack">
                    <label class="field">
                        <span class="field-label">{{ __('reports.schedule.fields.name') }}</span>
                        <input type="text" x-model="form.name" class="pos-input" maxlength="120"
                               placeholder="{{ __('reports.schedule.fields.name_placeholder') }}">
                    </label>

                    <div class="grid grid-cols-2 gap-3">
                        <label class="field">
                            <span class="field-label">{{ __('reports.schedule.fields.frequency') }}</span>
                            <select x-data="enhancedSelect({ dropdownParent: 'body' })" x-model="form.frequency" class="pos-input">
                                <option value="daily">{{ __('reports.schedule.frequencies.daily') }}</option>
                                <option value="weekly">{{ __('reports.schedule.frequencies.weekly') }}</option>
                                <option value="monthly">{{ __('reports.schedule.frequencies.monthly') }}</option>
                            </select>
                        </label>

                        <label class="field" x-show="form.frequency === 'weekly'" x-cloak>
                            <span class="field-label">{{ __('reports.schedule.fields.day_of_week') }}</span>
                            <select x-data="enhancedSelect({ dropdownParent: 'body' })" x-model="form.day_of_week" class="pos-input">
                                @for ($d = 1; $d <= 7; $d++)
                                    <option value="{{ $d }}">{{ __('reports.schedule.days.'.$d) }}</option>
                                @endfor
                            </select>
                        </label>

                        <label class="field" x-show="form.frequency === 'monthly'" x-cloak>
                            <span class="field-label">{{ __('reports.schedule.fields.day_of_month') }}</span>
                            <input type="number" x-model="form.day_of_month" class="pos-input" min="1" max="31">
                        </label>

                        <label class="field">
                            <span class="field-label">{{ __('reports.schedule.fields.time') }}</span>
                            <div class="time-picker" x-data="timePicker()" x-modelable="value" x-model="form.time_of_day"
                                 @click.outside="open = false">
                                <button type="button" class="pos-input time-picker-trigger" @click="toggle()" :aria-expanded="open">
                                    <span x-text="display"></span>
                                    <svg class="time-picker-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                </button>
                                <div class="time-picker-pop" x-show="open" x-cloak x-ref="pop">
                                    <div class="tp-col">
                                        <template x-for="h in hours" :key="'h' + h">
                                            <button type="button" class="tp-opt" :class="{ 'is-active': isHour(h) }" @click="setHour(h)" x-text="h"></button>
                                        </template>
                                    </div>
                                    <div class="tp-col">
                                        <template x-for="m in minutes" :key="'m' + m">
                                            <button type="button" class="tp-opt" :class="{ 'is-active': isMinute(m) }" @click="setMinute(m)" x-text="String(m).padStart(2, '0')"></button>
                                        </template>
                                    </div>
                                    <div class="tp-col">
                                        <template x-for="a in ['AM', 'PM']" :key="a">
                                            <button type="button" class="tp-opt" :class="{ 'is-active': isAmpm(a) }" @click="setAmpm(a)" x-text="a"></button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </label>
                    </div>

                    <label class="field">
                        <span class="field-label">{{ __('reports.schedule.fields.format') }}</span>
                        <select x-data="enhancedSelect({ dropdownParent: 'body' })" x-model="form.format" class="pos-input">
                            <option value="pdf">{{ __('reports.actions.export_pdf') }}</option>
                            <option value="xlsx">{{ __('reports.schedule.formats.xlsx') }}</option>
                            <option value="csv">{{ __('reports.schedule.formats.csv') }}</option>
                        </select>
                    </label>

                    <label class="field">
                        <span class="field-label">{{ __('reports.schedule.fields.recipients') }}</span>
                        <input type="text" x-model="form.recipients" class="pos-input"
                               placeholder="{{ __('reports.schedule.fields.recipients_placeholder') }}">
                        <p class="field-help">{{ __('reports.schedule.fields.recipients_hint') }}</p>
                    </label>

                    <label class="field">
                        <span class="field-label">{{ __('reports.schedule.fields.subject') }}</span>
                        <input type="text" x-model="form.subject" class="pos-input" maxlength="200"
                               placeholder="{{ __('reports.schedule.fields.subject_placeholder') }}">
                    </label>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="open = false" :disabled="saving">
                    {{ __('reports.schedule.cancel') }}
                </button>
                <button type="button" class="pos-btn pos-btn-sm pos-btn-primary" @click="save()" :disabled="saving">
                    <svg x-show="saving" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                    </svg>
                    {{ __('reports.schedule.save_confirm') }}
                </button>
            </div>
        </div>
    </div>
</div>
