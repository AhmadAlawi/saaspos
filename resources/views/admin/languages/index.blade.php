<x-admin-layout
    active="languages"
    :title="__('languages.title')"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => __('languages.crumb_parent')],
        ['label' => __('languages.title')],
    ]">

    <div class="page-wide"
         x-data="dataTable({ rowsSelector: 'tbody > tr[data-dt-row]' })">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">{{ __('languages.title') }}</h1>
                <p class="page-sub">{{ __('languages.sub') }}</p>
            </div>
            <a href="{{ route('admin.languages.create') }}" class="pos-btn pos-btn-sm pos-btn-primary">
                <x-icon name="plus" class="w-4 h-4" />
                {{ __('languages.actions.new') }}
            </a>
        </div>

        <div class="card card-pad-0">
            <div class="dt-toolbar">
                <div class="dt-toolbar-title">{{ __('languages.list.title', ['count' => $languages->count()]) }}</div>
                <x-admin.dt-search />
                <div class="dt-toolbar-info">{{ __('languages.list.keys', ['count' => $totalKeys]) }}</div>
                <x-admin.dt-toolbar-actions />
            </div>

            <div class="dt-scroll">
            <table class="dt-table">
                <thead>
                    <tr>
                        <th>{{ __('languages.columns.language') }}</th>
                        <th>{{ __('languages.columns.direction') }}</th>
                        <th>{{ __('languages.columns.progress') }}</th>
                        <th>{{ __('languages.columns.status') }}</th>
                        <th class="dt-actions-col"><span class="sr-only">{{ __('languages.columns.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($languages as $language)
                        @php $isBase = $language->code === 'en'; @endphp
                        <tr class="prod-row" data-dt-row
                            data-dt-name="{{ $language->native_name }}"
                            data-dt-id="{{ $language->id }}"
                            @click="window.location.href = {{ \Illuminate\Support\Js::from(route('admin.languages.edit', $language)) }}">
                            <td>
                                <div class="store-row-id">
                                    <div class="store-row-name-line">
                                        <span class="store-row-name">{{ $language->native_name }}</span>
                                        @if ($language->is_default)
                                            <span class="store-default-badge">{{ __('languages.badges.default') }}</span>
                                        @endif
                                    </div>
                                    <span class="store-row-code mono">{{ $language->name }} · {{ $language->code }}</span>
                                </div>
                            </td>
                            <td>
                                {{ $language->direction === 'rtl' ? __('languages.badges.rtl') : 'LTR' }}
                            </td>
                            <td>
                                <div class="lang-progress" title="{{ $language->progress }}%">
                                    <div class="lang-progress-track"><div class="lang-progress-fill" style="width: {{ $language->progress }}%"></div></div>
                                    <span class="lang-progress-pct tnum">{{ $language->progress }}%</span>
                                </div>
                            </td>
                            <td>
                                @if ($language->is_active)
                                    <span class="prod-badge prod-badge-positive">{{ __('languages.badges.active') }}</span>
                                @else
                                    <span class="prod-badge prod-badge-muted">{{ __('languages.badges.inactive') }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="prod-row-actions">
                                    @unless ($language->is_default)
                                        {{-- Active switch stays an inline quick-toggle. --}}
                                        <form method="POST" action="{{ route('admin.languages.toggle', $language) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="is_active" value="{{ $language->is_active ? '0' : '1' }}">
                                            <button type="submit" class="prod-status-btn" :class="{ 'is-on': {{ $language->is_active ? 'true' : 'false' }} }"
                                                    @click.stop aria-label="{{ __('languages.actions.deactivate') }}"
                                                    title="{{ $language->is_active ? __('languages.actions.deactivate') : __('languages.actions.activate') }}">
                                                <span class="prod-status-thumb" aria-hidden="true"></span>
                                            </button>
                                        </form>
                                    @endunless

                                    <x-admin.row-actions>
                                        <x-admin.row-action :href="route('admin.languages.edit', $language)" icon="edit" :label="__('table.action.edit')" />

                                        @unless ($isBase)
                                            {{-- Export translation template (English + current translations). --}}
                                            <x-admin.row-action :href="route('admin.languages.export', $language)" icon="download" :label="__('languages.actions.export')" />

                                            {{-- Import: pick a file → auto-submit. --}}
                                            <form method="POST" action="{{ route('admin.languages.import', $language) }}" enctype="multipart/form-data" x-data @click.stop>
                                                @csrf
                                                <input type="file" name="file" accept=".xlsx,.csv" class="hidden" x-ref="importFile" @change="$root.submit()">
                                                <button type="button" class="row-action" role="menuitem" @click="$refs.importFile.click()">
                                                    <span class="row-action-icon"><x-icon name="upload" class="w-4 h-4" /></span>
                                                    <span class="row-action-label">{{ __('languages.actions.import') }}</span>
                                                </button>
                                            </form>

                                            {{-- Auto-translate: machine-translates every key with no
                                                 existing translation yet, in the background — see
                                                 AutoTranslateLanguageJob. Doesn't touch rows someone
                                                 already hand-corrected via Export/Import. --}}
                                            <form method="POST" action="{{ route('admin.languages.auto-translate', $language) }}" @click.stop>
                                                @csrf
                                                <button type="submit" class="row-action" role="menuitem">
                                                    <span class="row-action-icon"><x-icon name="globe" class="w-4 h-4" /></span>
                                                    <span class="row-action-label">{{ __('languages.actions.auto_translate') }}</span>
                                                </button>
                                            </form>
                                        @endunless

                                        @unless ($language->is_default)
                                            <form method="POST" action="{{ route('admin.languages.default', $language) }}" @click.stop>
                                                @csrf @method('PATCH')
                                                <button type="submit" class="row-action" role="menuitem">
                                                    <span class="row-action-icon"><x-icon name="star" class="w-4 h-4" /></span>
                                                    <span class="row-action-label">{{ __('languages.actions.set_default') }}</span>
                                                </button>
                                            </form>
                                        @endunless

                                        @unless ($isBase || $language->is_default)
                                            <div class="row-action-sep"></div>
                                            <x-admin.row-action icon="trash" variant="danger" :label="__('languages.actions.delete')"
                                                @click="$store.confirm.show({
                                                    title: {{ \Illuminate\Support\Js::from(__('languages.delete.title', ['name' => $language->name])) }},
                                                    message: {{ \Illuminate\Support\Js::from(__('languages.delete.message')) }},
                                                    intent: 'danger',
                                                    confirmLabel: {{ \Illuminate\Support\Js::from(__('languages.actions.delete')) }},
                                                    cancelLabel: {{ \Illuminate\Support\Js::from(__('languages.actions.cancel')) }},
                                                    onConfirm: () => $deleteForm({{ \Illuminate\Support\Js::from(route('admin.languages.destroy', $language)) }}),
                                                })" />
                                        @endunless
                                    </x-admin.row-actions>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>

            <x-admin.dt-pager />
        </div>
    </div>
</x-admin-layout>
