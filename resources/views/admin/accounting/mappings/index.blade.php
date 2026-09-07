<x-admin-layout
    active="business-mappings"
    :title="__('accounting.mappings.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('accounting.nav.section')],
        ['label' => __('accounting.mappings.title')],
    ]">

    @php
        $canEdit = auth()->user()?->hasPermission('accounting.mappings.update');
        $typeOrder = ['asset', 'liability', 'equity', 'income', 'expense'];
        $accountsByType = $accounts->groupBy('type')->sortBy(fn ($g, $type) => array_search($type, $typeOrder));
    @endphp

    <div class="page-wide">
        @if (session('success'))
            <x-alert type="success" class="mb-4">{{ session('success') }}</x-alert>
        @endif

        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('accounting.mappings.title') }}</h1>
                <p class="page-sub">{{ __('accounting.mappings.sub') }}</p>
            </div>
        </div>

        @unless ($canEdit)
            <x-alert type="info" class="mb-4">{{ __('accounting.mappings.read_only') }}</x-alert>
        @endunless

        <form method="POST" action="{{ route('admin.accounting.mappings.update') }}" data-ajax-form class="card card-pad-0">
            @csrf
            @method('PATCH')

            <div class="map-list">
                @foreach ($mappings as $key => $mapping)
                    @php
                        $km    = __('accounting.mappings.keys.'.$key);
                        $label = is_array($km) ? ($km['label'] ?? '') : \Illuminate\Support\Str::headline($key);
                        $help  = is_array($km) ? ($km['help'] ?? '') : '';
                    @endphp
                    <div class="map-row field" :class="{ 'has-error': false }">
                        <div class="map-row-info">
                            <div class="map-row-label">{{ $label }}</div>
                            @if ($help)
                                <div class="map-row-help">{{ $help }}</div>
                            @endif
                        </div>
                        <div class="map-row-select">
                            <select name="mappings[{{ $key }}]" class="pos-input" x-data="enhancedSelect()" @disabled(! $canEdit)>
                                @foreach ($accountsByType as $type => $accts)
                                    <optgroup label="{{ __('accounting.types.'.$type) }}">
                                        @foreach ($accts as $a)
                                            <option value="{{ $a->id }}" @selected($mapping->account_id == $a->id)>{{ $a->code }} — {{ $a->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($canEdit)
                <div class="map-footer">
                    <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                        <x-icon name="check" class="w-4 h-4" />
                        {{ __('accounting.mappings.save') }}
                    </button>
                </div>
            @endif
        </form>
    </div>
</x-admin-layout>
