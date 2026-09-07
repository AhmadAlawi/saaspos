{{-- One row per tax group. Rendered on initial page load AND returned as
     `list_html` from the AJAX endpoints so the client swaps in place. --}}
@foreach ($rows as $row)
    @php
        $rateTotal = (float) $row->components->sum(fn ($c) => (float) $c->rate);
        $usage     = (int) ($row->products_count ?? 0) + (int) ($row->categories_count ?? 0);
    @endphp
    <tr class="prod-row"
        data-dt-row
        data-id="{{ $row->id }}"
        data-dt-name="{{ $row->name }}"
        data-dt-id="{{ $row->id }}"
        data-dt-classification="{{ $row->classification }}"
        data-dt-active="{{ $row->is_active ? '1' : '0' }}"
        :class="{ 'is-selected': form.id === {{ $row->id }} }"
        @click="openEdit({{ $row->id }})">
        <td class="mono">{{ $row->code }}</td>
        <td>
            {{ $row->name }}
            @if ($row->is_default)
                <span class="prod-badge prod-badge-info ms-1">{{ __('tax.groups.badges.default') }}</span>
            @endif
        </td>
        <td>
            <span class="prod-badge prod-badge-{{ in_array($row->classification, ['exempt','zero_rated','nil_rated'], true) ? 'muted' : 'positive' }}">
                {{ __('tax.groups.classifications.'.$row->classification) }}
            </span>
        </td>
        <td class="num tnum">{{ rtrim(rtrim(number_format($rateTotal, 4, '.', ''), '0'), '.') }}%</td>
        <td class="num tnum fg-tertiary">{{ $usage }}</td>
        <td>
            <span class="prod-badge"
                  :class="rowsById[{{ $row->id }}]?.is_active ? 'prod-badge-positive' : 'prod-badge-muted'"
                  x-text="rowsById[{{ $row->id }}]?.is_active ? @js(__('table.active')) : @js(__('table.inactive'))">
            </span>
        </td>
        <td>
            <div class="prod-row-actions">
                {{-- Status toggle — AJAX flip without opening the editor. --}}
                <button type="button"
                        class="prod-status-btn"
                        :class="{ 'is-on': rowsById[{{ $row->id }}]?.is_active }"
                        :disabled="togglingIds.includes({{ $row->id }})"
                        @click.stop="toggleActive({{ $row->id }})"
                        :aria-label="rowsById[{{ $row->id }}]?.is_active
                            ? @js(__('tax.groups.fields.is_active'))
                            : @js(__('tax.groups.badges.inactive'))"
                        :title="rowsById[{{ $row->id }}]?.is_active
                            ? @js(__('tax.groups.fields.is_active'))
                            : @js(__('tax.groups.badges.inactive'))">
                    <span class="prod-status-thumb" aria-hidden="true"></span>
                </button>

                <x-admin.row-actions>
                    <x-admin.row-action icon="edit" :label="__('table.action.edit')" @click="openEdit({{ $row->id }})" />
                    @if (! $row->is_default)
                        <div class="row-action-sep"></div>
                        <x-admin.row-action icon="trash" variant="danger" :label="__('tax.groups.actions.delete')"
                            @click="askDelete({{ $row->id }})" />
                    @endif
                </x-admin.row-actions>
            </div>
        </td>
    </tr>
@endforeach
