<x-admin-layout
    active="stock-takes"
    :title="__('stock_takes.new')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('stock_takes.crumb_parent')],
        ['label' => __('stock_takes.title'), 'href' => route('admin.inventory.stock-takes.index')],
        ['label' => __('stock_takes.new')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.inventory.stock-takes.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('stock_takes.title') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">{{ __('stock_takes.new') }}</h1>
                    <p class="page-sub">{{ __('stock_takes.create_sub') }}</p>
                </div>
            </div>
        </div>

        <form method="POST"
              action="{{ route('admin.inventory.stock-takes.store') }}"
              data-ajax-form>
            @csrf

            <div class="card">
                <div class="card-body">
                    <div class="form-stack">
                        <label class="field">
                            <span class="field-label is-required">{{ __('stock_takes.fields.store') }}</span>
                            <select name="store_id" class="pos-input" x-data="enhancedSelect()">
                                @foreach ($stores as $store)
                                    <option value="{{ $store->id }}" @selected($defaultStoreId === $store->id)>{{ $store->name }}</option>
                                @endforeach
                            </select>
                            <span class="field-help">{{ __('stock_takes.fields.store_help') }}</span>
                        </label>

                        <label class="field">
                            <span class="field-label is-required">{{ __('stock_takes.fields.take_date') }}</span>
                            <input type="text" name="take_date"
                                   value="{{ now()->toDateString() }}"
                                   class="pos-input js-datepicker">
                        </label>

                        <label class="field">
                            <span class="field-label">{{ __('stock_takes.fields.name') }}</span>
                            <input type="text" name="name" class="pos-input"
                                   maxlength="191"
                                   placeholder="{{ __('stock_takes.fields.name_placeholder') }}">
                            <span class="field-help">{{ __('stock_takes.fields.name_help') }}</span>
                        </label>

                        <label class="field">
                            <span class="field-label">{{ __('stock_takes.fields.notes') }}</span>
                            <textarea name="notes" class="pos-input" rows="3" maxlength="1000"
                                      placeholder="{{ __('stock_takes.fields.notes_placeholder') }}"></textarea>
                        </label>
                    </div>
                </div>
            </div>

            <div class="form-actions mt-5">
                <a href="{{ route('admin.inventory.stock-takes.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost">
                    {{ __('stock_takes.actions.cancel') }}
                </a>
                <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="plus" class="w-4 h-4" />
                    {{ __('stock_takes.actions.start_count') }}
                </button>
            </div>
        </form>
    </div>
</x-admin-layout>
