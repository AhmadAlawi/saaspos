<x-admin-layout
    active="settings"
    :title="__('receipt_templates.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('settings.title'), 'href' => route('admin.settings.index')],
        ['label' => __('receipt_templates.title')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('receipt_templates.title') }}</h1>
                <p class="page-sub">{{ __('receipt_templates.sub') }}</p>
            </div>
            <a href="{{ route('admin.receipt-templates.create') }}" class="pos-btn pos-btn-primary">
                <x-icon name="plus" class="w-4 h-4" />
                {{ __('receipt_templates.actions.create') }}
            </a>
        </div>

        <div class="card">
            <div class="card-body" style="overflow-x:auto;">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-muted text-xs uppercase">
                            <th class="pb-2">{{ __('receipt_templates.fields.name') }}</th>
                            <th class="pb-2">{{ __('receipt_templates.fields.paper_size') }}</th>
                            <th class="pb-2">{{ __('receipt_templates.fields.blocks') }}</th>
                            <th class="pb-2">{{ __('receipt_templates.fields.is_active') }}</th>
                            <th class="pb-2"></th>
                            <th class="pb-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $tpl)
                            <tr class="border-t border-subtle">
                                <td class="py-2">
                                    {{ $tpl->name }}
                                    @if ($tpl->is_default)
                                        <span class="prod-badge prod-badge-positive">{{ __('receipt_templates.fields.is_default') }}</span>
                                    @endif
                                </td>
                                <td class="py-2">{{ __('receipt_templates.paper_size.'.$tpl->paper_size) }}</td>
                                <td class="py-2">{{ $tpl->isCanvas() ? $tpl->elements_count : $tpl->blocks_count }}</td>
                                <td class="py-2">
                                    @if ($tpl->is_active)
                                        <span class="prod-badge prod-badge-positive">{{ __('receipt_templates.fields.is_active') }}</span>
                                    @else
                                        <span class="prod-badge prod-badge-muted">—</span>
                                    @endif
                                </td>
                                <td class="py-2">
                                    @if ($tpl->isCanvas())
                                        <a href="{{ route('admin.receipt-templates.canvas.edit', $tpl) }}" class="pos-btn pos-btn-sm pos-btn-primary">{{ __('receipt_templates.actions.edit_canvas') }}</a>
                                    @else
                                        <a href="{{ route('admin.receipt-templates.blocks.edit', $tpl) }}" class="pos-btn pos-btn-sm pos-btn-primary">{{ __('receipt_templates.actions.edit_blocks') }}</a>
                                    @endif
                                    <a href="{{ route('admin.receipt-templates.edit', $tpl) }}" class="pos-btn pos-btn-sm pos-btn-ghost">{{ __('receipt_templates.actions.edit') }}</a>
                                </td>
                                <td class="py-2 text-end">
                                    @unless ($tpl->is_default)
                                        <form method="POST" action="{{ route('admin.receipt-templates.default', $tpl) }}" class="contents">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="pos-btn pos-btn-sm pos-btn-ghost">{{ __('receipt_templates.actions.set_default') }}</button>
                                        </form>
                                    @endunless
                                    <form method="POST" action="{{ route('admin.receipt-templates.destroy', $tpl) }}" class="contents"
                                          onsubmit="return confirm({{ Js::from(__('receipt_templates.actions.confirm_delete')) }})">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="pos-btn pos-btn-sm pos-btn-danger">{{ __('receipt_templates.actions.delete') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-muted">{{ __('receipt_templates.empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">{{ $rows->links() }}</div>
    </div>
</x-admin-layout>
