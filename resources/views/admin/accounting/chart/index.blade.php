<x-admin-layout
    active="chart-of-accounts"
    :title="__('accounting.chart.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('accounting.nav.section')],
        ['label' => __('accounting.chart.title')],
    ]">

    <div class="page-wide"
         x-data="chartOfAccountsPage({
             storeUrl:               '{{ route('admin.accounting.chart-of-accounts.store') }}',
             updateUrlTemplate:      '{{ route('admin.accounting.chart-of-accounts.update', ['account' => '__ID__']) }}',
             deleteUrlTemplate:      '{{ route('admin.accounting.chart-of-accounts.destroy', ['account' => '__ID__']) }}',
             groupStoreUrl:          '{{ route('admin.accounting.chart-of-accounts.groups.store') }}',
             groupUpdateUrlTemplate: '{{ route('admin.accounting.chart-of-accounts.groups.update', ['group' => '__ID__']) }}',
             groupDeleteUrlTemplate: '{{ route('admin.accounting.chart-of-accounts.groups.destroy', ['group' => '__ID__']) }}',
             rows:                   {{ Js::from($rows) }},
             groups:                 {{ Js::from($groups) }},
             selectedId:             {{ Js::from($selectedId) }},
             parentPlaceholder:      {{ Js::from(__('accounting.chart.groups.fields.select_parent')) }},
         })">

        @if (session('success'))
            <x-alert type="success" class="mb-4">{{ session('success') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert type="danger" class="mb-4">{{ session('error') }}</x-alert>
        @endif

        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('accounting.chart.title') }}</h1>
                <p class="page-sub">{{ __('accounting.chart.sub') }}</p>
            </div>
            @if (auth()->user()?->hasPermission('accounting.chart.update'))
                <div class="flex items-center gap-2">
                    <button type="button" @click="openGroupNew()" class="pos-btn pos-btn-sm pos-btn-ghost">
                        <x-icon name="plus" class="w-4 h-4" />
                        {{ __('accounting.chart.groups.new') }}
                    </button>
                    <button type="button" @click="openNew()" class="pos-btn pos-btn-sm pos-btn-primary">
                        <x-icon name="plus" class="w-4 h-4" />
                        {{ __('accounting.chart.new') }}
                    </button>
                </div>
            @endif
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
            {{-- ── LEFT: the tree ─────────────────────────────────────── --}}
            <div class="card card-pad-0">
                <div class="coa-tree">
                    @include('admin.accounting.chart._tree', ['roots' => $roots, 'meta' => $meta])
                </div>
            </div>

            {{-- ── RIGHT: sticky editor ───────────────────────────────── --}}
            <div class="card rsn-editor">
                <div class="rsn-editor-empty" x-show="isIdle" x-cloak>
                    <span class="rsn-editor-empty-icon"><x-icon name="list" class="w-5 h-5" /></span>
                    <div class="rsn-editor-empty-title">{{ __('accounting.chart.editor.empty_title') }}</div>
                    <div class="rsn-editor-empty-sub">{{ __('accounting.chart.editor.empty_sub') }}</div>
                </div>

                <div x-show="isAccountMode" x-cloak>
                    <div>
                        <div class="rsn-editor-head">
                            <div>
                                <div class="rsn-editor-name">
                                    <span x-show="isNew">{{ __('accounting.chart.editor.new_title') }}</span>
                                    <span x-show="isEdit" x-text="form.name"></span>
                                </div>
                                <div class="rsn-editor-id">
                                    <span x-show="isEdit">
                                        {{ __('accounting.chart.editor.code_label') }}
                                        <span class="mono" x-text="form.code"></span>
                                    </span>
                                    <span x-show="isNew">{{ __('accounting.chart.editor.new_sub') }}</span>
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

                            <div class="form-stack">
                                <label class="field" :class="{ 'has-error': fieldErrors.code }">
                                    <span class="field-label is-required">{{ __('accounting.chart.fields.code') }}</span>
                                    <input type="text" name="code"
                                           x-model="form.code"
                                           :readonly="form.code_locked"
                                           :class="{ 'is-readonly': form.code_locked }"
                                           @input="clearFieldError('code')"
                                           class="pos-input mono" required maxlength="32">
                                    <p class="field-help" x-show="form.code_locked" x-cloak>{{ __('accounting.chart.fields.code_locked_help') }}</p>
                                    <p class="field-help" x-show="!form.code_locked">{{ __('accounting.chart.fields.code_help') }}</p>
                                </label>

                                <label class="field" :class="{ 'has-error': fieldErrors.name }">
                                    <span class="field-label is-required">{{ __('accounting.chart.fields.name') }}</span>
                                    <input type="text" name="name"
                                           x-model="form.name"
                                           @input="clearFieldError('name')"
                                           class="pos-input" required maxlength="255">
                                </label>

                                <label class="field" :class="{ 'has-error': fieldErrors.account_group_id }">
                                    <span class="field-label is-required">{{ __('accounting.chart.fields.group') }}</span>
                                    {{-- Already an enhanced (TomSelect) dropdown — built in JS by
                                         chart-of-accounts-page.js `_initGroupSelect()` because the
                                         page drives it through `$refs.group` / `_setGroup()`. Hence
                                         no `enhancedSelect` x-data here; do NOT add one (it would
                                         double-init). --}}
                                    <select name="account_group_id"
                                            x-ref="group"
                                            class="pos-input" required>
                                        <option value="">{{ __('accounting.chart.fields.select_group') }}</option>
                                        @foreach ($groupOptions as $g)
                                            <option value="{{ $g['id'] }}">{{ $g['label'] }}</option>
                                        @endforeach
                                    </select>
                                    <p class="field-help">{{ __('accounting.chart.fields.group_help') }}</p>
                                </label>

                                <label class="field-toggle">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" x-model="form.is_active">
                                    <span>{{ __('accounting.chart.fields.is_active') }}</span>
                                </label>

                                <p class="coa-system-note" x-show="form.is_system" x-cloak>
                                    <x-icon name="lock" class="w-4 h-4" />
                                    {{ __('accounting.chart.editor.system_note') }}
                                </p>
                            </div>

                            <div class="rsn-editor-footer">
                                <template x-if="isEdit && form.can_delete">
                                    <button type="button"
                                            @click="confirmDelete()"
                                            :disabled="submitting"
                                            class="pos-btn pos-btn-sm pos-btn-ghost pos-btn-danger">
                                        <x-icon name="trash" class="w-4 h-4" />
                                        {{ __('accounting.chart.actions.delete') }}
                                    </button>
                                </template>
                                <template x-if="!(isEdit && form.can_delete)">
                                    <span></span>
                                </template>

                                <div class="flex items-center gap-2">
                                    <button type="button"
                                            @click="closeEditor()"
                                            :disabled="submitting"
                                            class="pos-btn pos-btn-sm pos-btn-ghost">
                                        {{ __('accounting.chart.actions.discard') }}
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
                                        <span x-show="isEdit">{{ __('accounting.chart.actions.save') }}</span>
                                        <span x-show="isNew">{{ __('accounting.chart.actions.create') }}</span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- ── GROUP editor ─────────────────────────────────────── --}}
                <div x-show="isGroupMode" x-cloak>
                    <div class="rsn-editor-head">
                        <div>
                            <div class="rsn-editor-name">
                                <span x-show="isGroupNew">{{ __('accounting.chart.groups.editor.new_title') }}</span>
                                <span x-show="isGroupEdit" x-text="groupForm.name"></span>
                            </div>
                            <div class="rsn-editor-id">
                                <span x-show="isGroupNew">{{ __('accounting.chart.groups.editor.new_sub') }}</span>
                                <span x-show="isGroupEdit">{{ __('accounting.chart.groups.editor.edit_title') }}</span>
                            </div>
                        </div>
                    </div>

                    <form class="card-body" novalidate @submit.prevent="submitGroup()">
                        <div class="form-stack">
                            <label class="field" :class="{ 'has-error': fieldErrors.name }">
                                <span class="field-label is-required">{{ __('accounting.chart.groups.fields.name') }}</span>
                                <input type="text"
                                       x-model="groupForm.name"
                                       @input="clearFieldError('name')"
                                       class="pos-input" required maxlength="100">
                            </label>

                            <label class="field" :class="{ 'has-error': fieldErrors.parent_id }" x-show="!groupForm.is_system">
                                <span class="field-label is-required">{{ __('accounting.chart.groups.fields.parent') }}</span>
                                {{-- Already an enhanced (TomSelect) dropdown — built in JS by
                                     chart-of-accounts-page.js `_initParentSelect()`, whose options
                                     are repopulated per mode via `_setParentOptions()`. No
                                     `enhancedSelect` x-data here by design. --}}
                                <select x-ref="parent" class="pos-input">
                                    <option value="">{{ __('accounting.chart.groups.fields.select_parent') }}</option>
                                </select>
                                <p class="field-help">{{ __('accounting.chart.groups.fields.parent_help') }}</p>
                            </label>

                            <p class="coa-system-note" x-show="groupForm.is_system" x-cloak>
                                <x-icon name="lock" class="w-4 h-4" />
                                {{ __('accounting.chart.groups.system_note') }}
                            </p>
                        </div>

                        <div class="rsn-editor-footer">
                            <template x-if="isGroupEdit && groupForm.can_delete">
                                <button type="button"
                                        @click="confirmDeleteGroup()"
                                        :disabled="submitting"
                                        class="pos-btn pos-btn-sm pos-btn-ghost pos-btn-danger">
                                    <x-icon name="trash" class="w-4 h-4" />
                                    {{ __('accounting.chart.groups.actions.delete') }}
                                </button>
                            </template>
                            <template x-if="!(isGroupEdit && groupForm.can_delete)">
                                <span></span>
                            </template>

                            <div class="flex items-center gap-2">
                                <button type="button"
                                        @click="closeEditor()"
                                        :disabled="submitting"
                                        class="pos-btn pos-btn-sm pos-btn-ghost">
                                    {{ __('accounting.chart.groups.actions.discard') }}
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
                                    <span x-show="isGroupEdit">{{ __('accounting.chart.groups.actions.save') }}</span>
                                    <span x-show="isGroupNew">{{ __('accounting.chart.groups.actions.create') }}</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
