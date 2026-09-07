<x-admin-layout
    active="settings"
    :title="__('updates.title')"
    :crumbs="[
        ['label' => __('settings.crumb_parent')],
        ['label' => __('settings.title'), 'href' => route('admin.settings.index')],
        ['label' => __('updates.title')],
    ]">

    <div class="page-wide"
         x-data="updatesPage(@js([
            'available'   => (bool) $availableVersion,
            'version'     => $availableVersion,
            'release'     => $availableRelease,
            'lastChecked' => $lastChecked ? format_datetime($lastChecked) : null,
            'feedError'   => $feedError,
            'urls'        => [
                'check'     => route('admin.settings.updates.check'),
                'preflight' => route('admin.settings.updates.preflight'),
                'install'   => route('admin.settings.updates.install'),
                'skip'      => route('admin.settings.updates.skip'),
                'updates'   => route('admin.settings.updates.index'),
                'manualUpload'  => \Route::has('admin.settings.updates.manual.upload')  ? route('admin.settings.updates.manual.upload')  : null,
                'manualChunk'   => \Route::has('admin.settings.updates.manual.chunk')   ? route('admin.settings.updates.manual.chunk')   : null,
                'manualInstall' => \Route::has('admin.settings.updates.manual.install') ? route('admin.settings.updates.manual.install') : null,
            ],
            'zipOnly' => __('updates.manual.zip_only'),
         ]))">

        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.settings.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('settings.title') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">{{ __('updates.title') }}</h1>
                    <p class="page-sub">{{ __('updates.sub') }}</p>
                </div>
            </div>
            <button type="button" class="pos-btn pos-btn-sm pos-btn-secondary" @click="checkNow()" :disabled="busy || @js(pos_is_demo())">
                <x-icon name="refresh" class="w-4 h-4" />
                {{ __('updates.actions.check_now') }}
            </button>
        </div>

        {{-- Demo: updates are disabled end-to-end (server-blocked too). Show a
             clear banner and disable the check / upload / install controls so
             it never looks like a broken button. --}}
        @if (pos_is_demo())
            <div class="alert alert-warning mb-5">
                <span class="alert-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.86a2 2 0 0 1 3.4 0l8.39 14.6A2 2 0 0 1 20.39 22H3.61a2 2 0 0 1-1.7-3.54z"/></svg>
                </span>
                <div class="alert-body">
                    <div class="alert-msg">{{ __('updates.demo.locked') }}</div>
                </div>
            </div>
        @endif

        {{-- Feed unreachable --}}
        <div class="alert alert-warning mb-5" x-show="feedError" x-cloak>
            <span class="alert-icon"><x-icon name="alert" class="w-5 h-5" /></span>
            <div class="alert-body">
                <div class="alert-msg" x-text="feedError"></div>
            </div>
        </div>

        {{-- Update available --}}
        <div class="alert alert-info mb-5" x-show="available" x-cloak>
            <span class="alert-icon"><x-icon name="download" class="w-5 h-5" /></span>
            <div class="alert-body">
                <div class="alert-title" x-text="@js(__('updates.banner.title')).replace(':version', version || '')"></div>

                <template x-if="release?.highlights?.length">
                    <div class="mt-2">
                        <div class="text-sm font-semibold">{{ __('updates.banner.highlights') }}</div>
                        <ul class="alert-list mt-1">
                            <template x-for="item in release.highlights" :key="item">
                                <li x-text="item"></li>
                            </template>
                        </ul>
                    </div>
                </template>

                <template x-if="release?.breaking_changes?.length">
                    <div class="mt-2">
                        <div class="text-sm font-semibold fg-warning">{{ __('updates.banner.breaking') }}</div>
                        <ul class="alert-list mt-1">
                            <template x-for="item in release.breaking_changes" :key="item">
                                <li x-text="item"></li>
                            </template>
                        </ul>
                    </div>
                </template>

                <div class="alert-actions">
                    @can('updater.run')
                        <button type="button" class="pos-btn pos-btn-xs pos-btn-primary"
                                @click="openInstall()" :disabled="installBusy || @js(pos_is_demo())">
                            <x-icon name="download" class="w-4 h-4" />
                            {{ __('updates.banner.install') }}
                        </button>
                    @endcan
                    <template x-if="release?.size_bytes">
                        <span class="text-sm fg-secondary">
                            {{ __('updates.banner.size') }}:
                            <span x-text="(release.size_bytes / 1024 / 1024).toFixed(0) + ' MB'"></span>
                        </span>
                    </template>
                    <template x-if="release?.release_notes_url">
                        <a :href="release.release_notes_url" target="_blank" rel="noopener"
                           class="pos-btn pos-btn-xs pos-btn-ghost">
                            <x-icon name="external" class="w-4 h-4" />
                            {{ __('updates.banner.read_notes') }}
                        </a>
                    </template>
                    <button type="button" class="pos-btn pos-btn-xs pos-btn-ghost"
                            @click="skipVersion()" :disabled="busy || @js(pos_is_demo())">
                        {{ __('updates.banner.skip') }}
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">
            {{-- This install --}}
            <div class="card">
                <div class="card-header"><div>
                    <div class="card-title">{{ __('updates.sections.status') }}</div>
                    <div class="card-title-sub">{{ __('updates.sections.status_sub') }}</div>
                </div></div>
                <div class="card-body">
                    <div class="form-stack">
                        <div class="field">
                            <span class="field-label">{{ __('updates.fields.current_version') }}</span>
                            <p class="text-sm mono">{{ $currentVersion }}</p>
                        </div>
                        <div class="field">
                            <span class="field-label">{{ __('updates.fields.last_checked') }}</span>
                            <p class="text-sm fg-secondary">
                                <span x-show="lastChecked" x-text="lastChecked"></span>
                                <span x-show="!lastChecked">{{ __('updates.fields.never_checked') }}</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Preferences --}}
            <div class="card">
                <div class="card-header"><div>
                    <div class="card-title">{{ __('updates.sections.prefs') }}</div>
                    <div class="card-title-sub">{{ __('updates.sections.prefs_sub') }}</div>
                </div></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.settings.updates.update') }}" data-ajax-form>
                        @csrf
                        @method('PATCH')

                        {{-- Read-only in demo — the save endpoint is blocked too. --}}
                        <fieldset @disabled(pos_is_demo()) class="contents">
                        <div class="form-stack">
                            <label class="field">
                                <span class="field-label is-required">{{ __('updates.fields.channel') }}</span>
                                <select x-data="enhancedSelect()" name="update_channel" class="pos-input">
                                    @foreach ($channels as $c)
                                        <option value="{{ $c }}" @selected($channel === $c)>{{ __("updates.channels.$c") }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="field-toggle">
                                <input type="hidden" name="update_auto_check" value="0">
                                <input type="checkbox" name="update_auto_check" value="1" @checked($autoCheck)>
                                <span>{{ __('updates.fields.auto_check') }}</span>
                            </label>

                            <label class="field-toggle">
                                <input type="hidden" name="update_auto_install" value="0">
                                <input type="checkbox" name="update_auto_install" value="1" @checked($autoInstall)>
                                <span>{{ __('updates.fields.auto_install') }}</span>
                            </label>

                            <label class="field-toggle">
                                <input type="hidden" name="update_pinned" value="0">
                                <input type="checkbox" name="update_pinned" value="1" @checked($pinned)>
                                <span>{{ __('updates.fields.pin', ['version' => $currentVersion]) }}</span>
                            </label>

                            @if ($pinned)
                                <p class="text-sm fg-secondary">{{ __('updates.fields.pinned_note', ['version' => $pinnedVersion]) }}</p>
                            @endif

                            <div class="flex items-center justify-between">
                                <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">
                                    <x-icon name="check" class="w-4 h-4" />
                                    {{ __('updates.actions.save') }}
                                </button>
                                <a href="{{ route('admin.settings.updates.history') }}" class="pos-btn pos-btn-sm pos-btn-ghost">
                                    <x-icon name="clock" class="w-4 h-4" />
                                    {{ __('updates.actions.history') }}
                                </a>
                            </div>
                        </div>
                        </fieldset>
                    </form>
                </div>
            </div>
        </div>

        {{-- Manual update. Hidden if the manual routes aren't registered
             (e.g. a stale route cache from before they shipped) so the page
             degrades instead of 500-ing. --}}
        @can('updater.run')
            @if (\Route::has('admin.settings.updates.manual.upload'))
            <div class="card mt-5">
                <div class="card-header"><div>
                    <div class="card-title">{{ __('updates.manual.title') }}</div>
                    <div class="card-title-sub">{{ __('updates.manual.sub') }}</div>
                </div></div>
                <div class="card-body">
                    <div class="form-stack">
                        <div class="field">
                            <span class="field-label">{{ __('updates.manual.choose_file') }}</span>

                            <div class="img-upload">
                                <input type="file" accept=".zip" x-ref="manualInput"
                                       class="img-upload-input" @change="manualOnFile($event)">

                                <div class="img-upload-zone is-empty"
                                     :class="{ 'is-dragover': manualDragOver }"
                                     role="button" tabindex="0"
                                     @click="manualPick()"
                                     @keydown.enter.prevent="manualPick()"
                                     @keydown.space.prevent="manualPick()"
                                     @dragover.prevent="manualDragOver = true"
                                     @dragleave.prevent="manualDragOver = false"
                                     @drop.prevent="manualOnDrop($event)">
                                    <div class="img-upload-empty">
                                        <span class="img-upload-empty-icon">
                                            <x-icon name="upload" class="w-4 h-4" />
                                        </span>
                                        <span class="img-upload-empty-title"
                                              x-text="manualFileName || @js(__('updates.manual.dropzone'))"></span>
                                        <span class="img-upload-empty-sub">{{ __('updates.manual.dropzone_hint') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <button type="button" class="pos-btn pos-btn-sm pos-btn-secondary"
                                    @click="manualUpload()" :disabled="!manualFileName || manualBusy || @js(pos_is_demo())">
                                {{-- Spinner while the ZIP uploads + is validated; the
                                     upload can take a while on a big package, so show
                                     clear "running" feedback. --}}
                                <svg x-show="manualBusy" x-cloak class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"/>
                                </svg>
                                <span x-show="!manualBusy" class="inline-flex"><x-icon name="upload" class="w-4 h-4" /></span>
                                <span x-text="manualBusy ? (@js(__('updates.manual.uploading')) + (manualProgress ? ' ' + manualProgress + '%' : '')) : @js(__('updates.manual.upload'))"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        @endcan

        {{-- ───────────────────── Pre-flight modal ───────────────────── --}}
        <div x-show="installStep === 'preflight'" x-cloak class="overlay-host">
            <div class="scrim absolute inset-0" @click="cancelInstall()"></div>
            <div class="modal-card">
                <div class="modal-head">
                    <div>
                        <div class="modal-title">{{ __('updates.install.title') }} <span x-text="version"></span></div>
                        <div class="modal-sub">{{ __('updates.install.preflight') }}</div>
                    </div>
                    <button type="button" class="modal-x" @click="cancelInstall()" aria-label="{{ __('updates.install.cancel') }}">
                        <x-icon name="x" class="w-4 h-4" />
                    </button>
                </div>
                <div class="modal-body">
                    <div x-show="installBusy" class="text-sm fg-secondary">{{ __('updates.install.checking') }}</div>

                    <ul class="flex flex-col gap-2" x-show="!installBusy">
                        <template x-for="c in checks" :key="c.label">
                            <li class="flex items-start gap-2 text-sm">
                                <span class="mt-0.5 shrink-0" :class="c.passed ? 'text-[var(--positive)]' : 'text-[var(--danger)]'">
                                    <span x-show="c.passed"><x-icon name="check" class="w-4 h-4" /></span>
                                    <span x-show="!c.passed"><x-icon name="x" class="w-4 h-4" /></span>
                                </span>
                                <span>
                                    <span x-text="c.label"></span>
                                    <span class="block fg-tertiary" x-show="c.message" x-text="c.message"></span>
                                </span>
                            </li>
                        </template>
                    </ul>

                    <div class="alert alert-danger mt-3" x-show="!installBusy && !canInstall" x-cloak>
                        <span class="alert-icon"><x-icon name="alert" class="w-5 h-5" /></span>
                        <div class="alert-body"><div class="alert-msg">{{ __('updates.install.cannot') }}</div></div>
                    </div>

                    <div class="alert alert-warning mt-3" x-show="!installBusy && canInstall">
                        <span class="alert-icon"><x-icon name="alert" class="w-5 h-5" /></span>
                        <div class="alert-body"><div class="alert-msg">{{ __('updates.install.warning') }}</div></div>
                    </div>
                </div>
                <div class="modal-foot">
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="cancelInstall()">
                        {{ __('updates.install.cancel') }}
                    </button>
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-primary"
                            :disabled="!canInstall || installBusy || @js(pos_is_demo())" @click="runInstall()">
                        <x-icon name="download" class="w-4 h-4" />
                        {{ __('updates.install.install_now') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- ──────────────── Installing / done / failed overlay ──────────────── --}}
        <div x-show="installStep === 'installing' || installStep === 'done' || installStep === 'failed'"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 text-white text-center px-6">
            <div class="flex flex-col items-center gap-4 max-w-sm">
                <template x-if="installStep === 'installing'">
                    <div class="flex flex-col items-center gap-4">
                        <div class="h-12 w-12 animate-spin rounded-full border-4 border-white/25 border-t-white"></div>
                        <div class="text-lg font-semibold">{{ __('updates.install.installing') }}</div>
                        <div class="text-sm text-white/70">{{ __('updates.install.do_not') }}</div>
                    </div>
                </template>
                <template x-if="installStep === 'done'">
                    <div class="flex flex-col items-center gap-4">
                        <div class="h-12 w-12 flex items-center justify-center rounded-full bg-white/15">
                            <x-icon name="check" class="w-7 h-7" />
                        </div>
                        <div class="text-lg font-semibold">{{ __('updates.install.done') }}</div>
                        <div class="text-sm text-white/70">{{ __('updates.install.done_sub') }}</div>
                        <button type="button" class="pos-btn pos-btn-sm pos-btn-primary mt-2" @click="reload()">
                            {{ __('updates.install.reload') }}
                        </button>
                    </div>
                </template>
                <template x-if="installStep === 'failed'">
                    <div class="flex flex-col items-center gap-4">
                        <div class="h-12 w-12 flex items-center justify-center rounded-full bg-white/15">
                            <x-icon name="alert" class="w-7 h-7" />
                        </div>
                        <div class="text-lg font-semibold">{{ __('updates.install.failed') }}</div>
                        <div class="text-sm text-white/70" x-text="installError"></div>
                        <button type="button" class="pos-btn pos-btn-sm pos-btn-secondary mt-2" @click="installStep = null">
                            {{ __('updates.install.close') }}
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>
</x-admin-layout>
