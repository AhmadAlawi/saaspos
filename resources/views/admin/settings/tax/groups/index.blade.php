<x-admin-layout
    active="tax-groups"
    :title="__('tax.groups.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('tax.groups.title')],
    ]">

    @php
        $rowsForJs = $rows->map(fn ($g) => [
            'id'              => $g->id,
            'code'            => $g->code,
            'name'            => $g->name,
            'classification'  => $g->classification,
            'is_inclusive'    => (bool) $g->is_inclusive,
            'is_default'      => (bool) $g->is_default,
            'is_active'       => (bool) $g->is_active,
            'component_ids'   => $g->components->pluck('id')->map('intval')->values(),
            'component_codes' => $g->components->pluck('code')->values(),
            'products_count'  => (int) ($g->products_count ?? 0),
            'updated_at'      => $g->updated_at?->diffForHumans(),
        ])->values();

        $blank = [
            'mode'           => 'new',
            'id'             => null,
            'code'           => '',
            'name'           => '',
            'classification' => 'taxable',
            'is_inclusive'   => false,
            'is_default'     => false,
            'is_active'      => true,
            'component_ids'  => [],
        ];

        if ($errors->any()) {
            $editingId = old('editing_id') ? (int) old('editing_id') : null;
            $initial = [
                'mode'           => $editingId ? 'edit' : 'new',
                'id'             => $editingId,
                'code'           => (string) old('code', ''),
                'name'           => (string) old('name', ''),
                'classification' => (string) old('classification', 'taxable'),
                'is_inclusive'   => (bool) old('is_inclusive', false),
                'is_default'     => (bool) old('is_default', false),
                'is_active'      => (bool) old('is_active', true),
                'component_ids'  => array_values(array_map('intval', (array) old('component_ids', []))),
            ];
        } elseif ($selected) {
            $initial = [
                'mode'           => 'edit',
                'id'             => $selected->id,
                'code'           => $selected->code,
                'name'           => $selected->name,
                'classification' => $selected->classification,
                'is_inclusive'   => (bool) $selected->is_inclusive,
                'is_default'     => (bool) $selected->is_default,
                'is_active'      => (bool) $selected->is_active,
                'component_ids'  => $selected->components->pluck('id')->map('intval')->values()->all(),
            ];
        } elseif ($isNew) {
            $initial = $blank;
        } else {
            $initial = null;
        }

        $classifications = \App\Models\TaxClassification::ordered()->active()->get();
    @endphp

    <div class="page-wide"
         x-data="taxGroupsPage({
             storeUrl:              '{{ route('admin.settings.tax.groups.store') }}',
             updateUrlTemplate:     '{{ route('admin.settings.tax.groups.update', ['taxGroup' => '__ID__']) }}',
             deleteUrlTemplate:     '{{ route('admin.settings.tax.groups.destroy', ['taxGroup' => '__ID__']) }}',
             deleteInfoUrlTemplate:      '{{ route('admin.settings.tax.groups.delete-info', ['taxGroup' => '__ID__']) }}',
             deactivateCheckUrlTemplate: '{{ route('admin.settings.tax.groups.deactivate-check', ['taxGroup' => '__ID__']) }}',
             searchReplacementUrl:  '{{ route('admin.settings.tax.groups.search-replacement') }}',
             rows:                  {{ Js::from($rowsForJs) }},
             components:            {{ Js::from($components->map(fn ($c) => ['id' => $c->id, 'code' => $c->code, 'name' => $c->name, 'rate' => (string) $c->rate])->values()) }},
             initial:               {{ Js::from($initial) }},
         })">

        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('tax.groups.title') }}</h1>
                <p class="page-sub">{{ __('tax.groups.sub') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="dropdown" x-data="dropdown">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            @click="toggle()"
                            :aria-expanded="open">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('tax.groups.actions.export') }}
                        <x-icon name="chevron" class="w-4 h-4" />
                    </button>
                    <div class="dropdown-panel"
                         x-show="open"
                         x-cloak
                         @click.outside="close()"
                         @keydown.escape.window="close()">
                        <a href="{{ route('admin.settings.tax.groups.export', ['format' => 'csv']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('tax.groups.actions.export_csv') }}</span>
                        </a>
                        <a href="{{ route('admin.settings.tax.groups.export', ['format' => 'xlsx']) }}"
                           class="dropdown-item"
                           @click="close()">
                            <span class="dropdown-item-label">{{ __('tax.groups.actions.export_xlsx') }}</span>
                        </a>
                    </div>
                </div>

                <a href="{{ route('admin.settings.tax.classifications.index') }}"
                   class="pos-btn pos-btn-sm pos-btn-ghost">
                    <x-icon name="tag" class="w-4 h-4" />
                    {{ __('tax.groups.actions.manage_classifications') }}
                </a>

                <button type="button" @click="openNew()" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="plus" class="w-4 h-4" />
                    {{ __('tax.groups.new') }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_420px] gap-5 items-start">
            <div class="card card-pad-0">
                @if ($rows->isEmpty())
                    <div class="dt-empty">
                        <span class="dt-empty-icon"><x-icon name="tag" class="w-5 h-5" /></span>
                        <div class="dt-empty-title">{{ __('tax.groups.list.empty') }}</div>
                    </div>
                @else
                    <div class="dt-toolbar">
                        <div class="dt-toolbar-title">{{ __('tax.groups.list.title') }} (<span x-text="rowCount">{{ $rows->count() }}</span>)</div>
                        <x-admin.dt-search />
                        <x-admin.dt-toolbar-actions />
                    </div>
                    <div class="inv-filter inv-filter--ruled">
                        <label class="field flex-none basis-[220px]">
                            <span class="field-label">{{ __('tax.groups.columns.classification') }}</span>
                            <select class="pos-input"
                                    x-data="enhancedSelect()"
                                    x-model="classificationFilter"
                                    x-effect="ts && ts.setValue(classificationFilter)">
                                <option value="all">{{ __('tax.groups.list.filter_all') }}</option>
                                @foreach ($classifications as $cls)
                                    <option value="{{ $cls->slug }}">{{ $cls->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field inv-filter-select">
                            <span class="field-label">{{ __('tax.groups.list.filter_status_label') }}</span>
                            <select class="pos-input"
                                    x-data="enhancedSelect()"
                                    x-model="activeFilter"
                                    x-effect="ts && ts.setValue(activeFilter)">
                                <option value="all">{{ __('tax.groups.list.filter_all') }}</option>
                                <option value="active">{{ __('tax.groups.list.filter_active') }}</option>
                                <option value="inactive">{{ __('tax.groups.list.filter_inactive') }}</option>
                            </select>
                        </label>
                        <button type="button"
                                class="inv-filter-reset"
                                @click="resetFilters()">
                            <x-icon name="x" class="w-3.5 h-3.5" />
                            <span>{{ __('table.filter_reset') }}</span>
                        </button>
                    </div>
                    <div class="dt-scroll">
                    <table class="dt-table">
                        <thead>
                            <tr>
                                <th>{{ __('tax.groups.columns.code') }}</th>
                                <th>{{ __('tax.groups.columns.name') }}</th>
                                <th>{{ __('tax.groups.columns.classification') }}</th>
                                <th class="num">{{ __('tax.groups.columns.rate') }}</th>
                                <th class="num">{{ __('tax.groups.columns.usage') }}</th>
                                <th>{{ __('table.status') }}</th>
                                <th class="dt-actions-col"><span class="sr-only">{{ __('tax.groups.columns.actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="txg-list">
                            @include('admin.settings.tax.groups._list', ['rows' => $rows])
                        </tbody>
                    </table>
                    </div>
                    <x-admin.dt-pager />
                @endif
            </div>

            <div class="card rsn-editor">
                <div class="rsn-editor-empty" x-show="isIdle" x-cloak>
                    <span class="rsn-editor-empty-icon"><x-icon name="tag" class="w-5 h-5" /></span>
                    <div class="rsn-editor-empty-title">{{ __('tax.groups.editor.empty_title') }}</div>
                    <div class="rsn-editor-empty-sub">{{ __('tax.groups.editor.empty_sub') }}</div>
                </div>

                <template x-if="!isIdle">
                    <div>
                        <div class="rsn-editor-head">
                            <div>
                                <div class="rsn-editor-name"
                                     x-text="isEdit ? (form.name || @js(__('tax.groups.editor.edit_title')))
                                                    : @js(__('tax.groups.editor.new_title'))"></div>
                                <div class="rsn-editor-id">
                                    <template x-if="isEdit">
                                        <span>
                                            {{ __('tax.groups.editor.edit_id') }}
                                            <span class="mono" x-text="form.id"></span>
                                            <template x-if="rowMeta?.updated_at">
                                                <span> · {{ __('tax.groups.editor.edit_updated') }} <span x-text="rowMeta.updated_at"></span></span>
                                            </template>
                                        </span>
                                    </template>
                                    <template x-if="isNew">
                                        <span>{{ __('tax.groups.editor.new_sub') }}</span>
                                    </template>
                                </div>
                            </div>
                            <span class="prod-badge prod-badge-info"
                                  x-show="form.is_default" x-cloak>
                                {{ __('tax.groups.badges.default') }}
                            </span>
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
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="field" :class="{ 'has-error': fieldErrors.code }">
                                        <span class="field-label is-required">{{ __('tax.groups.fields.code') }}</span>
                                        <input type="text" name="code"
                                               x-model="form.code"
                                               @input="clearFieldError('code')"
                                               class="pos-input mono" required maxlength="32">
                                    </label>
                                    <label class="field" :class="{ 'has-error': fieldErrors.name }">
                                        <span class="field-label is-required">{{ __('tax.groups.fields.name') }}</span>
                                        <input type="text" name="name"
                                               x-model="form.name"
                                               @input="clearFieldError('name')"
                                               class="pos-input" required maxlength="100">
                                    </label>
                                </div>

                                <label class="field" :class="{ 'has-error': fieldErrors.classification }">
                                    <span class="field-label is-required">{{ __('tax.groups.fields.classification') }}</span>
                                    <select name="classification" class="pos-input" x-data="enhancedSelect()" x-model="form.classification">
                                        @foreach ($classifications as $cls)
                                            <option value="{{ $cls->slug }}">{{ $cls->name }}</option>
                                        @endforeach
                                    </select>
                                    <p class="field-help">{{ __('tax.groups.fields.classification_help') }}</p>
                                </label>

                                {{-- Multi-select dropdown for components. Options are server-
                                     rendered so TomSelect sees them at init time (Alpine x-for
                                     hadn't materialized them yet → empty dropdown bug). The
                                     x-effect pushes `form.component_ids` into the TomSelect
                                     instance whenever the editor opens a different row, so chips
                                     reflect the current selection. Order is preserved — receipts
                                     list components in the order the operator picks them. --}}
                                <label class="field" :class="{ 'has-error': fieldErrors.component_ids }">
                                    <span class="field-label">{{ __('tax.groups.fields.components') }}</span>
                                    @if ($components->isEmpty())
                                        <p class="fg-tertiary text-sm pos-input p-3 bg-[var(--bg-muted)]">
                                            {{ __('tax.groups.fields.components_empty') }}
                                        </p>
                                    @else
                                        <select name="component_ids[]" multiple
                                                class="pos-input"
                                                x-data="enhancedSelect({ maxOptions: 50, placeholder: @js(__('tax.groups.fields.components_picker_placeholder')) })"
                                                x-effect="ts && ts.setValue(form.component_ids, true)"
                                                x-model="form.component_ids">
                                            @foreach ($components as $c)
                                                @php
                                                    $rate = rtrim(rtrim(number_format((float) $c->rate, 4, '.', ''), '0'), '.');
                                                    $label = $c->code.' — '.$c->name.' ('.$rate.'%)';
                                                @endphp
                                                <option value="{{ $c->id }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    <p class="field-help">{{ __('tax.groups.fields.components_help') }}</p>
                                    <p class="txg-rate-total">
                                        {{ __('tax.groups.fields.rate_total_label') }}:
                                        <span class="mono num tnum txg-rate-total-value" x-text="rateTotalDisplay"></span>
                                    </p>
                                </label>

                                <div class="form-stack">
                                    <label class="field-toggle">
                                        <input type="hidden" name="is_inclusive" value="0">
                                        <input type="checkbox" name="is_inclusive" value="1" x-model="form.is_inclusive">
                                        <span>
                                            <span class="block">{{ __('tax.groups.fields.is_inclusive') }}</span>
                                            <span class="block text-[11.5px] fg-tertiary mt-0.5">{{ __('tax.groups.fields.is_inclusive_help') }}</span>
                                        </span>
                                    </label>
                                    <label class="field-toggle">
                                        <input type="hidden" name="is_default" value="0">
                                        <input type="checkbox" name="is_default" value="1" x-model="form.is_default">
                                        <span>
                                            <span class="block">{{ __('tax.groups.fields.is_default') }}</span>
                                            <span class="block text-[11.5px] fg-tertiary mt-0.5">{{ __('tax.groups.fields.is_default_help') }}</span>
                                        </span>
                                    </label>
                                    <label class="field-toggle">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" x-model="form.is_active">
                                        <span>{{ __('tax.groups.fields.is_active') }}</span>
                                    </label>
                                </div>
                            </div>

                            <div class="rsn-editor-footer">
                                <template x-if="isEdit && !form.is_default">
                                    <button type="button"
                                            @click="askDelete(form.id)"
                                            :disabled="submitting"
                                            class="pos-btn pos-btn-sm pos-btn-ghost pos-btn-danger">
                                        <x-icon name="trash" class="w-4 h-4" />
                                        {{ __('tax.groups.actions.delete') }}
                                    </button>
                                </template>
                                <template x-if="isNew || form.is_default">
                                    <span></span>
                                </template>

                                <div class="flex items-center gap-2">
                                    <button type="button"
                                            @click="closeEditor()"
                                            :disabled="submitting"
                                            class="pos-btn pos-btn-sm pos-btn-ghost">
                                        {{ __('tax.groups.actions.discard') }}
                                    </button>
                                    <button type="submit"
                                            class="pos-btn pos-btn-sm pos-btn-primary"
                                            :disabled="submitting">
                                        <svg x-show="submitting" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                        </svg>
                                        <span x-text="
                                            submitting
                                                ? (isEdit ? @js(__('tax.groups.actions.saving'))
                                                          : @js(__('tax.groups.actions.creating')))
                                                : (isEdit ? @js(__('tax.groups.actions.save'))
                                                          : @js(__('tax.groups.actions.create')))
                                        "></span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </template>
            </div>
        </div>

        {{-- Delete-with-move modal — same shape as Categories. Server-side
             search via remoteSelect; leaving the picker blank falls back to
             the system default group. --}}
        <div class="scrim overlay-host"
             x-show="delMove.open"
             x-cloak
             @click.self="closeDelMove()"
             @keydown.escape.window="if (delMove.open) closeDelMove()">
            <div class="modal-card" role="alertdialog" aria-modal="true"
                 :aria-labelledby="`txg-del-title-${delMove.id}`">
                <div class="modal-body">
                    <div class="confirm-title" :id="`txg-del-title-${delMove.id}`"
                         x-text="@js(__('tax.groups.delete_dialog.title')).replace(':name', delMove.name)"></div>
                    <div class="confirm-msg mt-2"
                         x-show="delMove.count > 0"
                         x-text="@js(__('tax.groups.delete_dialog.has_referrers')).replace(':count', delMove.count)"></div>
                    <div class="confirm-msg mt-2"
                         x-show="delMove.count === 0"
                         x-text="@js(__('tax.groups.delete_dialog.empty_confirm'))"></div>

                    <template x-if="delMove.count > 0">
                        <label class="field mt-4">
                            <span class="field-label">{{ __('tax.groups.delete_dialog.move_label') }}</span>
                            <template x-if="delMove.open">
                                <select class="pos-input"
                                        x-data="remoteSelect({
                                            url:            delMoveSearchUrl,
                                            exclude:        delMove.id,
                                            value:          delMove.replacementId,
                                            label:          delMove.replacementLabel,
                                            placeholder:    @js(__('tax.groups.delete_dialog.move_placeholder')),
                                            dropdownParent: 'body',
                                        })"
                                        x-model="delMove.replacementId">
                                    <option value="">—</option>
                                    <template x-if="delMove.replacementId">
                                        <option :value="delMove.replacementId" :selected="true"
                                                x-text="delMove.replacementLabel"></option>
                                    </template>
                                </select>
                            </template>
                            <p class="field-help" x-show="delMove.defaultName"
                               x-text="@js(__('tax.groups.delete_dialog.default_hint')).replace(':name', delMove.defaultName)"></p>
                        </label>
                    </template>
                </div>
                <div class="modal-foot">
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            :disabled="delMove.submitting"
                            @click="closeDelMove()">
                        {{ __('tax.groups.delete_dialog.cancel') }}
                    </button>
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-danger"
                            :disabled="delMove.submitting"
                            @click="confirmDelMove()">
                        <svg x-show="delMove.submitting" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                        </svg>
                        {{ __('tax.groups.delete_dialog.confirm') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
