<x-admin-layout
    active="expense-categories"
    :title="__('expense_categories.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('expenses.crumb_parent')],
        ['label' => __('expenses.title'), 'href' => route('admin.expenses.index')],
        ['label' => __('expense_categories.title')],
    ]">

    @php
        $rowsForJs = $rows->map(fn ($r) => [
            'id'        => $r->id,
            'name'      => $r->name,
            'is_active' => (bool) $r->is_active,
        ])->values();

        if ($errors->any()) {
            $editingId = old('editing_id') ? (int) old('editing_id') : null;
            $initial = [
                'mode'      => $editingId ? 'edit' : 'new',
                'id'        => $editingId,
                'name'      => (string) old('name', ''),
                'is_active' => (bool) old('is_active', true),
            ];
        } elseif ($selected) {
            $initial = [
                'mode'      => 'edit',
                'id'        => $selected->id,
                'name'      => $selected->name,
                'is_active' => (bool) $selected->is_active,
            ];
        } elseif ($isNew) {
            $initial = ['mode' => 'new', 'id' => null, 'name' => '', 'is_active' => true];
        } else {
            $initial = null;
        }
    @endphp

    <div class="page-wide"
         x-data="reasonsPage({
             storeUrl:          '{{ route('admin.expense-categories.store') }}',
             updateUrlTemplate: '{{ route('admin.expense-categories.update', ['expenseCategory' => '__ID__']) }}',
             deleteUrlTemplate: '{{ route('admin.expense-categories.destroy', ['expenseCategory' => '__ID__']) }}',
             rows:              {{ Js::from($rowsForJs) }},
             initial:           {{ Js::from($initial) }},
         })">

        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.expenses.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('expenses.title') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">{{ __('expense_categories.title') }}</h1>
                    <p class="page-sub">{{ __('expense_categories.sub') }}</p>
                </div>
            </div>
            <button type="button" @click="openNew()" class="pos-btn pos-btn-sm pos-btn-primary">
                <x-icon name="plus" class="w-4 h-4" />
                {{ __('expense_categories.new') }}
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px] gap-5 items-start">
            <div class="card card-pad-0">
                @if ($rows->isEmpty())
                    <div class="dt-empty">
                        <span class="dt-empty-icon"><x-icon name="tag" class="w-5 h-5" /></span>
                        <div class="dt-empty-title">{{ __('expense_categories.list.empty') }}</div>
                    </div>
                @else
                    <div class="dt-toolbar">
                        <div class="dt-toolbar-title">{{ __('expense_categories.list.title') }} (<span x-text="rowCount">{{ $rows->count() }}</span>)</div>
                        <x-admin.dt-search />
                        <x-admin.dt-toolbar-actions />
                    </div>
                    <div class="dt-scroll">
                    <table class="dt-table">
                        <thead>
                            <tr>
                                <th>{{ __('expense_categories.columns.name') }}</th>
                                <th>{{ __('table.status') }}</th>
                                <th class="dt-actions-col"><span class="sr-only">{{ __('expense_categories.columns.actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="rsn-list">
                            @include('admin.expense-categories._list', ['rows' => $rows])
                        </tbody>
                    </table>
                    </div>
                    <x-admin.dt-pager />
                @endif
            </div>

            <div class="card rsn-editor">
                <div class="rsn-editor-empty" x-show="isIdle" x-cloak>
                    <span class="rsn-editor-empty-icon"><x-icon name="tag" class="w-5 h-5" /></span>
                    <div class="rsn-editor-empty-title">{{ __('expense_categories.editor.empty_title') }}</div>
                    <div class="rsn-editor-empty-sub">{{ __('expense_categories.editor.empty_sub') }}</div>
                </div>

                <template x-if="!isIdle">
                    <div>
                        <div class="rsn-editor-head">
                            <div>
                                <div class="rsn-editor-name"
                                     x-text="isEdit ? (form.name || @js(__('expense_categories.editor.edit_title')))
                                                    : @js(__('expense_categories.editor.new_title'))"></div>
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
                                <label class="field" :class="{ 'has-error': fieldErrors.name }">
                                    <span class="field-label is-required">{{ __('expense_categories.fields.name') }}</span>
                                    <input type="text" name="name"
                                           x-model="form.name"
                                           @input="clearFieldError('name')"
                                           class="pos-input" required maxlength="100" autofocus>
                                </label>

                                <label class="field-toggle">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" x-model="form.is_active">
                                    <span>{{ __('expense_categories.fields.is_active') }}</span>
                                </label>
                            </div>

                            <div class="rsn-editor-footer">
                                <template x-if="isEdit">
                                    <button type="button"
                                            @click="confirmDelete()"
                                            :disabled="submitting"
                                            class="pos-btn pos-btn-sm pos-btn-ghost pos-btn-danger">
                                        <x-icon name="trash" class="w-4 h-4" />
                                        {{ __('expense_categories.actions.delete') }}
                                    </button>
                                </template>
                                <template x-if="isNew"><span></span></template>

                                <div class="flex items-center gap-2">
                                    <button type="button" @click="closeEditor()" :disabled="submitting" class="pos-btn pos-btn-sm pos-btn-ghost">
                                        {{ __('expense_categories.actions.discard') }}
                                    </button>
                                    <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary" :disabled="submitting">
                                        <svg x-show="submitting" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                        </svg>
                                        <span x-text="submitting
                                            ? (isEdit ? @js(__('expense_categories.actions.saving')) : @js(__('expense_categories.actions.creating')))
                                            : (isEdit ? @js(__('expense_categories.actions.save')) : @js(__('expense_categories.actions.create')))"></span>
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
