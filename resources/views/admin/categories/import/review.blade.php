<x-admin-layout
    active="categories"
    :title="__('categories.import.title')"
    :crumbs="[
        ['label' => __('categories.crumb_parent')],
        ['label' => __('categories.title'), 'href' => route('admin.categories.index')],
        ['label' => __('categories.import.title'), 'href' => route('admin.categories.import')],
        ['label' => __('categories.import.review_title')],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.categories.import') }}"
                   class="pos-btn pos-btn-sm pos-btn-ghost"
                   aria-label="{{ __('categories.import.back') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">{{ __('categories.import.review_title') }}</h1>
                    <p class="page-sub">{{ __('categories.import.review_sub') }}</p>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.categories.import.commit') }}">
            @csrf
            <input type="hidden" name="staged_path" value="{{ $stagedPath }}">

            <div class="card mb-5">
                <div class="card-body">
                    <table class="prod-import-map">
                        <thead>
                            <tr>
                                <th>{{ __('categories.import.col_header') }}</th>
                                <th>{{ __('categories.import.col_maps_to') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($preview['headers'] as $i => $header)
                                <tr>
                                    <td class="prod-import-header">{{ $header !== '' ? $header : 'Column '.($i + 1) }}</td>
                                    <td>
                                        <select name="mapping[{{ $i }}]" class="pos-input" x-data="enhancedSelect()">
                                            <option value="">{{ __('categories.import.ignore_column') }}</option>
                                            @foreach ($targetFields as $field)
                                                <option value="{{ $field }}"
                                                    @selected(($preview['suggested_map'][$header] ?? null) === $field)>
                                                    {{ __("categories.import.fields.{$field}") }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mb-5">
                <div class="card-header">
                    <div>
                        <div class="card-title">
                            {{ __('categories.import.preview_table', ['count' => count($preview['rows'])]) }}
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    @if (empty($preview['rows']))
                        <p class="fg-tertiary">{{ __('categories.import.no_data') }}</p>
                    @else
                        <div class="prod-import-preview-wrap">
                            <table class="prod-import-preview">
                                <thead>
                                    <tr>
                                        @foreach ($preview['headers'] as $h)
                                            <th>{{ $h }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($preview['rows'] as $row)
                                        <tr>
                                            @for ($i = 0; $i < $preview['total_columns']; $i++)
                                                <td>{{ $row[$i] ?? '' }}</td>
                                            @endfor
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex items-center justify-end gap-2">
                <a href="{{ route('admin.categories.import') }}"
                   class="pos-btn pos-btn-sm pos-btn-ghost">
                    {{ __('categories.import.cancel') }}
                </a>
                <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="check" class="w-4 h-4" />
                    {{ __('categories.import.commit_button') }}
                </button>
            </div>
        </form>
    </div>
</x-admin-layout>
