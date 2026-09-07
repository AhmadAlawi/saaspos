<x-admin-layout
    active="settings"
    :title="$template->exists ? __('receipt_templates.edit.title') : __('receipt_templates.create.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('receipt_templates.title'), 'href' => route('admin.receipt-templates.index')],
        ['label' => $template->exists ? __('receipt_templates.edit.title') : __('receipt_templates.create.title')],
    ]">

    <div class="page-wide" style="max-width:560px;">
        <div class="page-header mb-6">
            <h1 class="page-title">{{ $template->exists ? __('receipt_templates.edit.title') : __('receipt_templates.create.title') }}</h1>
        </div>

        <form method="POST" action="{{ $template->exists ? route('admin.receipt-templates.update', $template) : route('admin.receipt-templates.store') }}" class="card">
            @csrf
            @if ($template->exists) @method('PATCH') @endif

            <div class="card-body form-stack">
                <label class="field">
                    <span class="field-label is-required">{{ __('receipt_templates.fields.name') }}</span>
                    <input type="text" name="name" class="pos-input" value="{{ old('name', $template->name) }}" required maxlength="150">
                    @error('name') <p class="field-error">{{ $message }}</p> @enderror
                </label>

                <label class="field">
                    <span class="field-label is-required">{{ __('receipt_templates.fields.paper_size') }}</span>
                    <select name="paper_size" class="pos-input">
                        @foreach (['58mm', '80mm', 'a4'] as $size)
                            <option value="{{ $size }}" @selected(old('paper_size', $template->paper_size) === $size)>
                                {{ __('receipt_templates.paper_size.'.$size) }}
                            </option>
                        @endforeach
                    </select>
                    @error('paper_size') <p class="field-error">{{ $message }}</p> @enderror
                </label>

                <label class="field-toggle">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template->is_active ?? true))>
                    <span>{{ __('receipt_templates.fields.is_active') }}</span>
                </label>

                @unless ($template->exists)
                    {{-- One-time choice — layout_mode can't change once a
                         template has content (blocks vs. free-positioned
                         elements are different data shapes entirely). --}}
                    <div class="field">
                        <span class="field-label is-required">{{ __('receipt_templates.fields.layout_mode') }}</span>
                        <label class="field-toggle" style="align-items:flex-start;">
                            <input type="radio" name="layout_mode" value="blocks" @checked(old('layout_mode', 'blocks') === 'blocks')>
                            <span>
                                <strong>{{ __('receipt_templates.layout_mode.blocks') }}</strong><br>
                                <span class="text-muted text-xs">{{ __('receipt_templates.layout_mode.blocks_help') }}</span>
                            </span>
                        </label>
                        <label class="field-toggle" style="align-items:flex-start;">
                            <input type="radio" name="layout_mode" value="canvas" @checked(old('layout_mode') === 'canvas')>
                            <span>
                                <strong>{{ __('receipt_templates.layout_mode.canvas') }}</strong><br>
                                <span class="text-muted text-xs">{{ __('receipt_templates.layout_mode.canvas_help') }}</span>
                            </span>
                        </label>
                    </div>
                @endunless
            </div>

            <div class="card-footer flex items-center justify-end gap-2">
                <a href="{{ route('admin.receipt-templates.index') }}" class="pos-btn pos-btn-ghost">{{ __('receipt_templates.actions.cancel') }}</a>
                <button type="submit" class="pos-btn pos-btn-primary">{{ __('receipt_templates.actions.save') }}</button>
            </div>
        </form>
    </div>
</x-admin-layout>
