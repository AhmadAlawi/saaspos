<x-admin-layout
    active="settings"
    :title="__('receipt_templates.blocks.title', ['name' => $template->name])"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('receipt_templates.title'), 'href' => route('admin.receipt-templates.index')],
        ['label' => $template->name],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('receipt_templates.blocks.title', ['name' => $template->name]) }}</h1>
                <p class="page-sub">{{ __('receipt_templates.blocks.sub') }}</p>
            </div>
            <a href="{{ route('admin.receipt-templates.index') }}" class="pos-btn pos-btn-ghost">{{ __('receipt_templates.blocks.back') }}</a>
        </div>

        <div class="flex items-start gap-4" style="align-items:flex-start;">
        <div style="flex:1 1 0; min-width:0; max-width:760px;">

        <div class="card">
            <div class="card-body" x-data="sortableList({
                    url: {{ Js::from(route('admin.receipt-templates.blocks.reorder', $template)) }},
                    handle: '.rtpl-block-handle',
                })">
                @foreach ($blocks as $block)
                    <div data-id="{{ $block->id }}" class="rtpl-block-row" style="border:1px solid var(--pos-border, #e5e7eb); border-radius:8px; padding:12px; margin-bottom:8px;">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="rtpl-block-handle" style="cursor:grab; color:var(--pos-muted,#9ca3af);" title="{{ __('receipt_templates.blocks.add') }}">⠿</span>
                                <strong>{{ __('receipt_templates.blocks.types.'.$block->type) }}</strong>
                                <span class="prod-badge {{ $block->isStructural() ? 'prod-badge-muted' : 'prod-badge-positive' }}">
                                    {{ $block->isStructural() ? __('receipt_templates.blocks.structural') : __('receipt_templates.blocks.custom') }}
                                </span>
                            </div>

                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('admin.receipt-templates.blocks.update', [$template, $block]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <label class="field-toggle">
                                        <input type="hidden" name="is_visible" value="0">
                                        <input type="checkbox" name="is_visible" value="1" onchange="this.form.submit()" @checked($block->is_visible)>
                                        <span>{{ __('receipt_templates.blocks.visible') }}</span>
                                    </label>
                                </form>

                                @unless ($block->isStructural())
                                    <form method="POST" action="{{ route('admin.receipt-templates.blocks.destroy', [$template, $block]) }}"
                                          onsubmit="return confirm({{ Js::from(__('receipt_templates.blocks.confirm_remove')) }})">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="pos-btn pos-btn-sm pos-btn-danger">{{ __('receipt_templates.blocks.remove') }}</button>
                                    </form>
                                @endunless
                            </div>
                        </div>

                        @if ($block->type === 'text')
                            <form method="POST" action="{{ route('admin.receipt-templates.blocks.update', [$template, $block]) }}" class="mt-2">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="is_visible" value="{{ $block->is_visible ? 1 : 0 }}">
                                <label class="field">
                                    <textarea name="text" class="pos-input" rows="3" dir="auto"
                                              placeholder="{{ __('receipt_templates.blocks.text_placeholder') }}">{{ old('text', $block->config['text'] ?? '') }}</textarea>
                                </label>
                                <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary mt-1">{{ __('receipt_templates.blocks.save') }}</button>
                            </form>
                        @endif

                        @if ($block->type === 'image')
                            <form method="POST" action="{{ route('admin.receipt-templates.blocks.update', [$template, $block]) }}" class="mt-2" enctype="multipart/form-data">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="is_visible" value="{{ $block->is_visible ? 1 : 0 }}">
                                @if (! empty($block->config['image_path']))
                                    <div class="mb-2">
                                        <div class="text-muted text-xs">{{ __('receipt_templates.blocks.image_current') }}</div>
                                        <img src="{{ \Illuminate\Support\Facades\Storage::url($block->config['image_path']) }}" alt="" style="max-height:60px;">
                                        <label class="field-toggle">
                                            <input type="checkbox" name="image_remove" value="1">
                                            <span>{{ __('receipt_templates.blocks.image_remove') }}</span>
                                        </label>
                                    </div>
                                @endif
                                <input type="file" name="image" accept="image/png,image/jpeg,image/webp" class="pos-input">
                                <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary mt-1">{{ __('receipt_templates.blocks.save') }}</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-body flex items-center gap-2" style="flex-wrap:wrap;">
                <strong class="mr-2">{{ __('receipt_templates.blocks.add') }}:</strong>
                @foreach (['text' => 'add_text', 'image' => 'add_image', 'divider' => 'add_divider', 'spacer' => 'add_spacer'] as $type => $labelKey)
                    <form method="POST" action="{{ route('admin.receipt-templates.blocks.store', $template) }}" class="contents">
                        @csrf
                        <input type="hidden" name="type" value="{{ $type }}">
                        <button type="submit" class="pos-btn pos-btn-sm pos-btn-ghost">{{ __('receipt_templates.blocks.'.$labelKey) }}</button>
                    </form>
                @endforeach
            </div>
        </div>

        </div>

        <div style="flex:1 1 0; min-width:320px; max-width:420px; position:sticky; top:16px;">
            <div class="card">
                <div class="card-body" style="padding:0; overflow:hidden; border-radius:8px;">
                    <div class="text-muted text-xs" style="padding:8px 12px;">{{ __('receipt_templates.blocks.preview') }}</div>
                    <iframe src="{{ route('admin.receipt-templates.preview', $template) }}"
                            style="width:100%; height:70vh; border:0; border-top:1px solid var(--pos-border, #e5e7eb);"
                            title="{{ __('receipt_templates.blocks.preview') }}"></iframe>
                </div>
            </div>
        </div>
        </div>
    </div>
</x-admin-layout>
