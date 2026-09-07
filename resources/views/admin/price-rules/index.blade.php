<x-admin-layout
    active="price-rules"
    :title="__('price_rules.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('price_rules.title')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('price_rules.title') }}</h1>
                <p class="page-sub">{{ __('price_rules.sub') }}</p>
            </div>
            <a href="{{ route('admin.price-rules.create') }}" class="pos-btn pos-btn-primary">
                <x-icon name="plus" class="w-4 h-4" />
                {{ __('price_rules.actions.create') }}
            </a>
        </div>

        <div class="card">
            <div class="card-body" style="overflow-x:auto;">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-muted text-xs uppercase">
                            <th class="pb-2">{{ __('price_rules.fields.name') }}</th>
                            <th class="pb-2">{{ __('price_rules.fields.scope') }}</th>
                            <th class="pb-2">{{ __('price_rules.fields.store') }}</th>
                            <th class="pb-2 text-end">{{ __('price_rules.fields.discount') }}</th>
                            <th class="pb-2">{{ __('price_rules.fields.window') }}</th>
                            <th class="pb-2">{{ __('price_rules.fields.status') }}</th>
                            <th class="pb-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $rule)
                            @php
                                $running = $rule->isRunning($now);
                                $scopeLabel = match ($rule->scope) {
                                    \App\Models\PriceRule::SCOPE_PRODUCT  => $rule->product?->name ?? __('price_rules.scope.product'),
                                    \App\Models\PriceRule::SCOPE_CATEGORY => $rule->category?->name ?? __('price_rules.scope.category'),
                                    default                                => __('price_rules.scope.all'),
                                };
                            @endphp
                            <tr class="border-t border-subtle">
                                <td class="py-2">{{ $rule->name }}</td>
                                <td class="py-2">
                                    <span class="prod-badge prod-badge-muted">{{ __('price_rules.scope.'.$rule->scope) }}</span>
                                    <span class="text-muted">{{ $scopeLabel }}</span>
                                </td>
                                <td class="py-2">{{ $rule->store?->name ?? __('price_rules.all_stores') }}</td>
                                <td class="py-2 text-end mono tnum">
                                    {{ $rule->discount_type === 'pct' ? rtrim(rtrim($rule->discount_value, '0'), '.').'%' : format_money($rule->discount_value) }}
                                </td>
                                <td class="py-2">{{ format_datetime($rule->starts_at) }} &rarr; {{ format_datetime($rule->ends_at) }}</td>
                                <td class="py-2">
                                    @if (! $rule->is_active)
                                        <span class="prod-badge prod-badge-muted">{{ __('price_rules.status.disabled') }}</span>
                                    @elseif ($running)
                                        <span class="prod-badge prod-badge-positive">{{ __('price_rules.status.running') }}</span>
                                    @elseif ($rule->starts_at > $now)
                                        <span class="prod-badge prod-badge-warning">{{ __('price_rules.status.scheduled') }}</span>
                                    @else
                                        <span class="prod-badge prod-badge-muted">{{ __('price_rules.status.ended') }}</span>
                                    @endif
                                </td>
                                <td class="py-2 text-end">
                                    <a href="{{ route('admin.price-rules.edit', $rule) }}" class="pos-btn pos-btn-sm pos-btn-ghost">{{ __('price_rules.actions.edit') }}</a>
                                    <form method="POST" action="{{ route('admin.price-rules.destroy', $rule) }}" class="contents"
                                          onsubmit="return confirm({{ Js::from(__('price_rules.actions.confirm_delete')) }})">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="pos-btn pos-btn-sm pos-btn-danger">{{ __('price_rules.actions.delete') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-muted">{{ __('price_rules.empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">{{ $rows->links() }}</div>
    </div>
</x-admin-layout>
