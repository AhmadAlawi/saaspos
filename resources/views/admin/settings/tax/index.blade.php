<x-admin-layout
    active="tax"
    :title="__('tax.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('tax.title')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('tax.title') }}</h1>
                <p class="page-sub">{{ __('tax.sub') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            {{-- Components --}}
            <a href="{{ route('admin.settings.tax.components.index') }}" class="card hover:shadow transition">
                <div class="card-body">
                    <div class="flex items-center gap-3">
                        <span class="settings-card-icon"><x-icon name="tag" class="w-5 h-5" /></span>
                        <div class="flex-1">
                            <div class="card-title">{{ __('tax.hub.components.title') }}</div>
                            <div class="card-title-sub">{{ __('tax.hub.components.sub') }}</div>
                        </div>
                        <span class="prod-badge prod-badge-muted tnum">{{ $componentCount }}</span>
                    </div>
                </div>
            </a>

            {{-- Groups --}}
            <a href="{{ route('admin.settings.tax.groups.index') }}" class="card hover:shadow transition">
                <div class="card-body">
                    <div class="flex items-center gap-3">
                        <span class="settings-card-icon"><x-icon name="tag" class="w-5 h-5" /></span>
                        <div class="flex-1">
                            <div class="card-title">{{ __('tax.hub.groups.title') }}</div>
                            <div class="card-title-sub">
                                {{ __('tax.hub.groups.sub') }}
                                @if ($defaultGroup)
                                    <span class="block text-[11.5px] fg-tertiary mt-0.5">
                                        {{ __('tax.hub.groups.default_hint', ['name' => $defaultGroup->name]) }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <span class="prod-badge prod-badge-muted tnum">{{ $groupCount }}</span>
                    </div>
                </div>
            </a>
        </div>
    </div>
</x-admin-layout>
