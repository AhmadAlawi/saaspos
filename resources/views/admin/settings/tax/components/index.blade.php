<x-admin-layout
    active="tax-components"
    :title="__('tax.components.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('tax.components.title')],
    ]">

    @php
        $rowsForJs = $rows->map(fn ($r) => [
            'id'         => $r->id,
            'code'       => $r->code,
            'name'       => $r->name,
            'rate'       => (string) $r->rate,
            'is_active'  => (bool) $r->is_active,
            'updated_at' => $r->updated_at?->diffForHumans(),
        ])->values();

        if ($errors->any()) {
            $editingId = old('editing_id') ? (int) old('editing_id') : null;
            $initial = [
                'mode'      => $editingId ? 'edit' : 'new',
                'id'        => $editingId,
                'code'      => (string) old('code', ''),
                'name'      => (string) old('name', ''),
                'rate'      => (string) old('rate', '0'),
                'is_active' => (bool) old('is_active', true),
            ];
        } elseif ($selected) {
            $initial = [
                'mode'      => 'edit',
                'id'        => $selected->id,
                'code'      => $selected->code,
                'name'      => $selected->name,
                'rate'      => (string) $selected->rate,
                'is_active' => (bool) $selected->is_active,
            ];
        } elseif ($isNew) {
            $initial = ['mode' => 'new', 'id' => null, 'code' => '', 'name' => '', 'rate' => '0', 'is_active' => true];
        } else {
            $initial = null;
        }
    @endphp

    <div class="page-wide"
         x-data="taxComponentsPage({
             storeUrl:          '{{ route('admin.settings.tax.components.store') }}',
             updateUrlTemplate: '{{ route('admin.settings.tax.components.update', ['taxComponent' => '__ID__']) }}',
             deleteUrlTemplate:          '{{ route('admin.settings.tax.components.destroy', ['taxComponent' => '__ID__']) }}',
             deactivateCheckUrlTemplate: '{{ route('admin.settings.tax.components.deactivate-check', ['taxComponent' => '__ID__']) }}',
             rows:              {{ Js::from($rowsForJs) }},
             initial:           {{ Js::from($initial) }},
         })">

        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('tax.components.title') }}</h1>
                <p class="page-sub">{{ __('tax.components.sub') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="dropdown" x-data="dropdown">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            @click="toggle()"
                            :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('tax.components.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel"
                         x-show="open"
                         x-cloak
                         @click.outside="close()"
                         @keydown.escape.window="close()">
                        <a href="{{ route('admin.settings.tax.components.export', ['format' => 'csv']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('tax.components.actions.export_csv') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.tax.components.export', ['format' => 'xlsx']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('tax.components.actions.export_xlsx') }}</span>
                        </a>
                    </div>
                </div>

                <button type="button" @click="openNew()" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="plus" class="w-4 h-4" />
                    {{ __('tax.components.new') }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
            {{-- ── LEFT: list ──────────────────────────────────────────── --}}
            <div class="card card-pad-0">
                @if ($rows->isEmpty())
                    <div class="dt-empty">
                        <span class="dt-empty-icon"><x-icon name="tag" class="w-5 h-5" /></span>
                        <div class="dt-empty-title">{{ __('tax.components.list.empty') }}</div>
                    </div>
                @else
                    <div class="dt-toolbar">
                        <div class="dt-toolbar-title">{{ __('tax.components.list.title') }} (<span x-text="rowCount">{{ $rows->count() }}</span>)</div>
                        <x-admin.dt-search />
                        <x-admin.dt-toolbar-actions />
                    </div>
                    <div class="dt-scroll">
                    <table class="dt-table">
                        <thead>
                            <tr>
                                <th>{{ __('tax.components.columns.code') }}</th>
                                <th>{{ __('tax.components.columns.name') }}</th>
                                <th class="num">{{ __('tax.components.columns.rate') }}</th>
                                <th>{{ __('table.status') }}</th>
                                <th class="dt-actions-col"><span class="sr-only">{{ __('tax.components.columns.actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="txc-list">
                            @include('admin.settings.tax.components._list', ['rows' => $rows])
                        </tbody>
                    </table>
                    </div>
                    <x-admin.dt-pager />
                @endif
            </div>

            {{-- ── RIGHT: sticky editor ───────────────────────────────── --}}
            <div class="card rsn-editor">
                <div class="rsn-editor-empty" x-show="isIdle" x-cloak>
                    <span class="rsn-editor-empty-icon"><x-icon name="tag" class="w-5 h-5" /></span>
                    <div class="rsn-editor-empty-title">{{ __('tax.components.editor.empty_title') }}</div>
                    <div class="rsn-editor-empty-sub">{{ __('tax.components.editor.empty_sub') }}</div>
                </div>

                <template x-if="!isIdle">
                    <div>
                        <div class="rsn-editor-head">
                            <div>
                                <div class="rsn-editor-name"
                                     x-text="isEdit ? (form.name || @js(__('tax.components.editor.edit_title')))
                                                    : @js(__('tax.components.editor.new_title'))"></div>
                                <div class="rsn-editor-id">
                                    <template x-if="isEdit">
                                        <span>
                                            {{ __('tax.components.editor.edit_id') }}
                                            <span class="mono" x-text="form.id"></span>
                                            <template x-if="rowMeta?.updated_at">
                                                <span> · {{ __('tax.components.editor.edit_updated') }} <span x-text="rowMeta.updated_at"></span></span>
                                            </template>
                                        </span>
                                    </template>
                                    <template x-if="isNew">
                                        <span>{{ __('tax.components.editor.new_sub') }}</span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <form method="POST"
                              :action="formAction"
                              class="card-body"
                              novalidate
                              @submit.prevent="submitEditor($event)">
                            @csrf
                            <input type="hidden" name="_method" :value="isEdit ? 'PATCH' : 'POST'">
                            <input type="hidden" name="editing_id" :value="form.id || ''">

                            <div class="form-stack">
                                <label class="field" :class="{ 'has-error': fieldErrors.code }">
                                    <span class="field-label is-required">{{ __('tax.components.fields.code') }}</span>
                                    <input type="text" name="code"
                                           x-model="form.code"
                                           @input="clearFieldError('code')"
                                           class="pos-input mono" required maxlength="32">
                                    <p class="field-help">{{ __('tax.components.fields.code_help') }}</p>
                                </label>

                                <label class="field" :class="{ 'has-error': fieldErrors.name }">
                                    <span class="field-label is-required">{{ __('tax.components.fields.name') }}</span>
                                    <input type="text" name="name"
                                           x-model="form.name"
                                           @input="clearFieldError('name')"
                                           class="pos-input" required maxlength="100">
                                </label>

                                <label class="field" :class="{ 'has-error': fieldErrors.rate }">
                                    <span class="field-label is-required">{{ __('tax.components.fields.rate') }}</span>
                                    <div class="flex items-center gap-2">
                                        <input type="number" name="rate"
                                               x-model="form.rate"
                                               @input="clearFieldError('rate')"
                                               class="pos-input tnum" required min="0" max="100" step="0.0001">
                                        <span class="fg-tertiary">%</span>
                                    </div>
                                    <p class="field-help">{{ __('tax.components.fields.rate_help') }}</p>
                                </label>

                                <label class="field-toggle">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" x-model="form.is_active">
                                    <span>{{ __('tax.components.fields.is_active') }}</span>
                                </label>
                            </div>

                            <div class="rsn-editor-footer">
                                <template x-if="isEdit">
                                    <button type="button"
                                            @click="confirmDelete()"
                                            :disabled="submitting"
                                            class="pos-btn pos-btn-sm pos-btn-ghost pos-btn-danger">
                                        <x-icon name="trash" class="w-4 h-4" />
                                        {{ __('tax.components.actions.delete') }}
                                    </button>
                                </template>
                                <template x-if="isNew">
                                    <span></span>
                                </template>

                                <div class="flex items-center gap-2">
                                    <button type="button"
                                            @click="closeEditor()"
                                            :disabled="submitting"
                                            class="pos-btn pos-btn-sm pos-btn-ghost">
                                        {{ __('tax.components.actions.discard') }}
                                    </button>
                                    <button type="submit"
                                            class="pos-btn pos-btn-sm pos-btn-primary"
                                            :disabled="submitting">
                                        <svg x-show="submitting" x-cloak
                                             class="h-4 w-4 animate-spin"
                                             viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                        </svg>
                                        <span x-text="
                                            submitting
                                                ? (isEdit ? @js(__('tax.components.actions.saving'))
                                                          : @js(__('tax.components.actions.creating')))
                                                : (isEdit ? @js(__('tax.components.actions.save'))
                                                          : @js(__('tax.components.actions.create')))
                                        "></span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </template>
            </div>
        </div>
    </div>
</x-admin-layout>
