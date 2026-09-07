<x-admin-layout
    active="settings"
    :title="__('settings.backup.title')"
    :crumbs="[
        ['label' => __('settings.crumb_parent')],
        ['label' => __('settings.title'), 'href' => route('admin.settings.index')],
        ['label' => __('settings.backup.title')],
    ]">

    @php
        $b = [
            'enabled'         => (bool) old('backup_enabled',         $company->backup_enabled),
            'frequency'       => old('backup_frequency',              $company->backup_frequency ?: 'weekly'),
            'retention_days'  => (int) old('backup_retention_days',   $company->backup_retention_days ?: 30),
            'target_disk'     => old('backup_target_disk',            $company->backup_target_disk ?: 'local'),
            'include_uploads' => (bool) old('backup_include_uploads', $company->backup_include_uploads ?? true),
            'email_enabled'   => (bool) old('backup_email_enabled',   $company->backup_email_enabled ?? false),
        ];
    @endphp

    <div class="page-wide">
        <x-admin.demo-lock-banner>{{ __('settings.demo.locked_banner_generic') }}</x-admin.demo-lock-banner>

        {{-- AJAX via lib/ajax-form.js. --}}
        <form method="POST" action="{{ route('admin.settings.backup.update') }}" data-ajax-form>
            @csrf
            @method('PATCH')
            {{-- On the demo, `disabled` makes the whole form read-only; `contents`
                 keeps the existing layout intact. --}}
            <fieldset @disabled(pos_is_demo()) class="contents">

            <div class="page-header mb-6">
                <div class="flex items-start gap-3">
                    <a href="{{ route('admin.settings.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('settings.title') }}">
                        <x-icon name="back" class="w-4 h-4" />
                    </a>
                    <div>
                        <h1 class="page-title">{{ __('settings.backup.title') }}</h1>
                        <p class="page-sub">{{ __('settings.backup.sub') }}</p>
                    </div>
                </div>
                <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                    <x-icon name="check" class="w-4 h-4" />
                    {{ __('settings.backup.actions.save') }}
                </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">
                {{-- Schedule --}}
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('settings.backup.sections.schedule') }}</div>
                        <div class="card-title-sub">{{ __('settings.backup.sections.schedule_sub') }}</div>
                    </div></div>
                    <div class="card-body">
                        <div class="form-stack">
                            <label class="field-toggle">
                                <input type="hidden" name="backup_enabled" value="0">
                                <input type="checkbox" name="backup_enabled" value="1" @checked($b['enabled'])>
                                <span>{{ __('settings.backup.fields.enabled') }}</span>
                            </label>

                            <label class="field">
                                <span class="field-label is-required">{{ __('settings.backup.fields.frequency') }}</span>
                                <select x-data="enhancedSelect()" name="backup_frequency" class="pos-input">
                                    @foreach ($frequencies as $f)
                                        <option value="{{ $f }}" @selected($b['frequency'] === $f)>{{ __("settings.backup.frequencies.$f") }}</option>
                                    @endforeach
                                </select>
                                @error('backup_frequency')<p class="field-error">{{ $message }}</p>@enderror
                            </label>

                            <label class="field">
                                <span class="field-label is-required">{{ __('settings.backup.fields.retention_days') }}</span>
                                <input type="number" name="backup_retention_days" value="{{ $b['retention_days'] }}"
                                       class="pos-input" min="1" max="3650">
                                @error('backup_retention_days')<p class="field-error">{{ $message }}</p>@enderror
                            </label>

                            <label class="field-toggle">
                                <input type="hidden" name="backup_email_enabled" value="0">
                                <input type="checkbox" name="backup_email_enabled" value="1" @checked($b['email_enabled'])>
                                <span>{{ __('settings.backup.fields.email_enabled') }}</span>
                            </label>
                            <span class="field-help">{{ __('settings.backup.fields.email_help') }}</span>

                            @if ($company->backup_last_run_at)
                                <div class="field">
                                    <span class="field-label">{{ __('settings.backup.fields.last_run_at') }}</span>
                                    <p class="text-sm fg-secondary">{{ format_datetime($company->backup_last_run_at) }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Storage --}}
                <div class="card">
                    <div class="card-header"><div>
                        <div class="card-title">{{ __('settings.backup.sections.storage') }}</div>
                        <div class="card-title-sub">{{ __('settings.backup.sections.storage_sub') }}</div>
                    </div></div>
                    <div class="card-body">
                        <div class="form-stack">
                            <label class="field">
                                <span class="field-label is-required">{{ __('settings.backup.fields.target_disk') }}</span>
                                <select x-data="enhancedSelect()" name="backup_target_disk" class="pos-input">
                                    @foreach ($disks as $key => $label)
                                        <option value="{{ $key }}" @selected($b['target_disk'] === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('backup_target_disk')<p class="field-error">{{ $message }}</p>@enderror
                            </label>

                            <label class="field-toggle">
                                <input type="hidden" name="backup_include_uploads" value="0">
                                <input type="checkbox" name="backup_include_uploads" value="1" @checked($b['include_uploads'])>
                                <span>{{ __('settings.backup.fields.include_uploads') }}</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            </fieldset>
        </form>

        {{-- Run now --}}
        <div class="card mt-5">
            <div class="card-header"><div>
                <div class="card-title">{{ __('settings.backup.sections.manual') }}</div>
                <div class="card-title-sub">{{ __('settings.backup.sections.manual_sub') }}</div>
            </div></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.settings.backup.run') }}" data-ajax-form>
                    @csrf
                    <button type="submit" class="pos-btn pos-btn-sm pos-btn-secondary" @disabled(pos_is_demo())>
                        <x-icon name="database" class="w-4 h-4" />
                        {{ __('settings.backup.actions.run_now') }}
                    </button>
                </form>
            </div>
        </div>

        {{-- Files --}}
        <div class="card mt-5">
            <div class="card-header"><div>
                <div class="card-title">{{ __('settings.backup.sections.files') }}</div>
                <div class="card-title-sub">{{ __('settings.backup.sections.files_sub') }}</div>
            </div></div>
            @if (empty($files))
                <div class="card-body">
                    <p class="fg-tertiary">{{ __('settings.backup.files.empty') }}</p>
                </div>
            @else
                <table class="dt-table">
                    <thead>
                        <tr>
                            <th>{{ __('settings.backup.files.name') }}</th>
                            <th>{{ __('settings.backup.files.size') }}</th>
                            <th>{{ __('settings.backup.files.created') }}</th>
                            <th class="dt-actions-col"><span class="sr-only">{{ __('settings.backup.files.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($files as $f)
                            <tr>
                                <td class="mono">{{ $f['name'] }}</td>
                                <td class="tnum">{{ number_format($f['size'] / 1024 / 1024, 2) }} MB</td>
                                <td>{{ format_datetime(\Carbon\Carbon::createFromTimestamp($f['modified_at'])) }}</td>
                                <td>
                                    <x-admin.row-actions>
                                        <x-admin.row-action :href="route('admin.settings.backup.download', $f['name'])" icon="download" :label="__('settings.backup.actions.download')" />
                                        @unless (pos_is_demo())
                                            <div class="row-action-sep"></div>
                                            <x-admin.row-action icon="trash" variant="danger" :label="__('settings.backup.actions.delete')"
                                                @click="$store.confirm.show({
                                                    title: {{ \Illuminate\Support\Js::from(__('settings.backup.confirm_delete', ['name' => $f['name']])) }},
                                                    intent: 'danger',
                                                    confirmLabel: {{ \Illuminate\Support\Js::from(__('settings.backup.actions.delete')) }},
                                                    onConfirm: () => $deleteForm({{ \Illuminate\Support\Js::from(route('admin.settings.backup.destroy', $f['name'])) }}),
                                                })" />
                                        @endunless
                                    </x-admin.row-actions>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Restore — a full data overwrite, so it's hidden on the public demo. --}}
        @can('backup.restore')
        @unless (pos_is_demo())
            <div class="card mt-5">
                <div class="card-header"><div>
                    <div class="card-title">{{ __('settings.backup.sections.restore') }}</div>
                    <div class="card-title-sub">{{ __('settings.backup.sections.restore_sub') }}</div>
                </div></div>
                <div class="card-body">
                    <a href="{{ route('admin.settings.restore.index') }}" class="pos-btn pos-btn-sm pos-btn-danger">
                        <x-icon name="refresh" class="w-4 h-4" />
                        {{ __('settings.backup.actions.restore') }}
                    </a>
                </div>
            </div>
        @endunless
        @endcan
    </div>
</x-admin-layout>
