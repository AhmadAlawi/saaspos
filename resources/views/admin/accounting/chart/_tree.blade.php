{{-- The whole chart of accounts, root groups down. Returned verbatim as
     `tree_html` from the AJAX endpoints so the client can swap it in. --}}
@forelse ($roots as $group)
    @include('admin.accounting.chart._group', ['group' => $group, 'meta' => $meta, 'level' => 0])
@empty
    <div class="dt-empty">
        <span class="dt-empty-icon"><x-icon name="list" class="w-5 h-5" /></span>
        <div class="dt-empty-title">{{ __('accounting.chart.empty.title') }}</div>
        <div class="dt-empty-sub">{{ __('accounting.chart.empty.sub') }}</div>
    </div>
@endforelse
