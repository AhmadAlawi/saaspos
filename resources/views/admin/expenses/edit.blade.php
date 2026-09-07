<x-admin-layout
    active="expenses"
    :title="$expense->exists ? $expense->number : __('expenses.new')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('expenses.crumb_parent')],
        ['label' => __('expenses.title'), 'href' => route('admin.expenses.index')],
        ['label' => $expense->exists ? $expense->number : __('expenses.new')],
    ]">

    @php
        $isEdit = $expense->exists;
        $action = $isEdit ? route('admin.expenses.update', $expense) : route('admin.expenses.store');
    @endphp

    <div class="page-wide">
        <form method="POST" action="{{ $action }}" novalidate data-ajax-form>
            @csrf
            @if ($isEdit) @method('PATCH') @endif

            <div class="page-header mb-6">
                <div class="flex items-start gap-3">
                    <a href="{{ route('admin.expenses.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('expenses.title') }}">
                        <x-icon name="back" class="w-4 h-4" />
                    </a>
                    <div>
                        <h1 class="page-title">{{ $isEdit ? $expense->number : __('expenses.new') }}</h1>
                        <p class="page-sub">{{ __('expenses.sub') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.expenses.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost">{{ __('expenses.actions.discard') }}</a>
                    <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                        <x-icon name="check" class="w-4 h-4" />
                        {{ $isEdit ? __('expenses.actions.save') : __('expenses.actions.create') }}
                    </button>
                </div>
            </div>

            <div class="max-w-2xl">
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('expenses.sections.details') }}</div>
                        <div class="card-title-sub">{{ __('expenses.sections.details_sub') }}</div>
                    </div></div>
                    <div class="card-body">
                        <div class="form-stack">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="field">
                                    <span class="field-label is-required">{{ __('expenses.fields.date') }}</span>
                                    <input type="text" name="expense_date"
                                           value="{{ old('expense_date', optional($expense->expense_date)->format('Y-m-d')) }}"
                                           class="pos-input js-datepicker" autocomplete="off" required>
                                    @error('expense_date')<p class="field-error">{{ $message }}</p>@enderror
                                </label>
                                <label class="field">
                                    <span class="field-label">{{ __('expenses.fields.category') }}</span>
                                    <select name="category_id" class="pos-input" x-data="enhancedSelect()">
                                        <option value="">{{ __('expenses.fields.no_category') }}</option>
                                        @foreach ($categories as $c)
                                            <option value="{{ $c->id }}" @selected((int) old('category_id', $expense->category_id) === (int) $c->id)>{{ $c->name }}</option>
                                        @endforeach
                                    </select>
                                    @can('viewAny', App\Models\ExpenseCategory::class)
                                        <p class="field-help">
                                            <a href="{{ route('admin.expense-categories.index') }}" class="link" target="_blank">{{ __('expenses.fields.manage_categories') }}</a>
                                        </p>
                                    @endcan
                                </label>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="field">
                                    <span class="field-label is-required">{{ __('expenses.fields.amount') }}</span>
                                    <input type="number" name="amount" step="0.0001" min="0"
                                           value="{{ old('amount', $expense->amount) }}" class="pos-input num tnum" required>
                                    @error('amount')<p class="field-error">{{ $message }}</p>@enderror
                                </label>
                                <label class="field">
                                    <span class="field-label">{{ __('expenses.fields.tax_amount') }}</span>
                                    <input type="number" name="tax_amount" step="0.0001" min="0"
                                           value="{{ old('tax_amount', $expense->tax_amount) }}" class="pos-input num tnum">
                                    @error('tax_amount')<p class="field-error">{{ $message }}</p>@enderror
                                </label>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="field">
                                    <span class="field-label">{{ __('expenses.fields.payment_method') }}</span>
                                    <select name="payment_method_id" class="pos-input" x-data="enhancedSelect()">
                                        <option value="">{{ __('expenses.fields.no_payment_method') }}</option>
                                        @foreach ($paymentMethods as $pm)
                                            <option value="{{ $pm->id }}" @selected((int) old('payment_method_id', $expense->payment_method_id) === (int) $pm->id)>{{ $pm->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="field">
                                    <span class="field-label">{{ __('expenses.fields.supplier') }}</span>
                                    <select name="supplier_id" class="pos-input" x-data="enhancedSelect()">
                                        <option value="">{{ __('expenses.fields.no_supplier') }}</option>
                                        @foreach ($suppliers as $sup)
                                            <option value="{{ $sup->id }}" @selected((int) old('supplier_id', $expense->supplier_id) === (int) $sup->id)>{{ $sup->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>

                            <label class="field">
                                <span class="field-label">{{ __('expenses.fields.reference') }}</span>
                                <input type="text" name="reference" value="{{ old('reference', $expense->reference) }}" class="pos-input" maxlength="191" placeholder="{{ __('expenses.fields.reference_help') }}">
                                @error('reference')<p class="field-error">{{ $message }}</p>@enderror
                            </label>

                            <label class="field">
                                <span class="field-label">{{ __('expenses.fields.description') }}</span>
                                <textarea name="description" rows="3" class="pos-input" maxlength="2000">{{ old('description', $expense->description) }}</textarea>
                                @error('description')<p class="field-error">{{ $message }}</p>@enderror
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
