<x-admin-layout
    active="settings"
    :title="__('restore.title')"
    :crumbs="[
        ['label' => __('settings.crumb_parent')],
        ['label' => __('settings.title'), 'href' => route('admin.settings.index')],
        ['label' => __('settings.backup.title'), 'href' => route('admin.settings.backup.edit')],
        ['label' => __('restore.crumb')],
    ]">

    <div class="page-wide" x-cloak
         x-data="restoreWizard(@js([
            'urls' => [
                'validate' => route('admin.settings.restore.validate'),
                'run'      => route('admin.settings.restore.store'),
                'login'    => url('/login'),
            ],
            'hasBackups' => count($backups) > 0,
         ]))">

        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.settings.backup.edit') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="{{ __('settings.backup.title') }}">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">{{ __('restore.title') }}</h1>
                    <p class="page-sub">{{ __('restore.sub') }}</p>
                </div>
            </div>
        </div>

        {{-- Step indicator --}}
        <ol class="flex items-center gap-3 mb-6 text-sm">
            <li :class="step === 'source' ? 'font-semibold' : 'fg-tertiary'">1. {{ __('restore.steps.source') }}</li>
            <li class="fg-tertiary">&rsaquo;</li>
            <li :class="step === 'review' ? 'font-semibold' : 'fg-tertiary'">2. {{ __('restore.steps.review') }}</li>
            <li class="fg-tertiary">&rsaquo;</li>
            <li :class="step === 'confirm' ? 'font-semibold' : 'fg-tertiary'">3. {{ __('restore.steps.confirm') }}</li>
        </ol>

        {{-- ─────────────────────────── STEP 1: SOURCE ─────────────────────────── --}}
        <div x-show="step === 'source'" class="space-y-5">

            {{-- Existing local backups --}}
            <div class="card">
                <div class="card-header"><div>
                    <div class="card-title">{{ __('restore.source.existing_title') }}</div>
                    <div class="card-title-sub">{{ __('restore.source.existing_sub') }}</div>
                </div></div>

                @if (empty($backups))
                    <div class="card-body">
                        <p class="fg-tertiary">{{ __('restore.source.empty') }}</p>
                    </div>
                @else
                    <div class="dt-scroll">
                    <table class="dt-table">
                        <thead>
                            <tr>
                                <th class="w-10"><span class="sr-only">{{ __('restore.steps.source') }}</span></th>
                                <th>{{ __('restore.source.col_created') }}</th>
                                <th>{{ __('restore.source.col_version') }}</th>
                                <th>{{ __('restore.source.col_size') }}</th>
                                <th>{{ __('restore.source.col_type') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($backups as $b)
                                <tr class="cursor-pointer" @click="pickBackup({{ $b['id'] }})">
                                    <td>
                                        <input type="radio" name="restore_source" value="{{ $b['id'] }}"
                                               x-model.number="selected" @click.stop="pickBackup({{ $b['id'] }})">
                                    </td>
                                    <td>{{ $b['created_at'] ? format_datetime($b['created_at']) : '—' }}</td>
                                    <td class="mono">{{ $b['version'] ?? '—' }}</td>
                                    <td class="tnum">{{ $b['size'] ? number_format($b['size'] / 1024 / 1024, 2).' MB' : '—' }}</td>
                                    <td>{{ $b['type'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                @endif
            </div>

            {{-- Upload a backup --}}
            <div class="card">
                <div class="card-header"><div>
                    <div class="card-title">{{ __('restore.source.upload_title') }}</div>
                    <div class="card-title-sub">{{ __('restore.source.upload_sub') }}</div>
                </div></div>
                <div class="card-body">
                    <label class="field">
                        <span class="field-label">{{ __('restore.source.choose_file') }}</span>
                        <input type="file" accept=".zip" class="pos-input" @change="onFile($event)">
                    </label>
                    <p class="text-sm fg-secondary mt-2" x-show="fileName" x-text="fileName"></p>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="button" class="pos-btn pos-btn-sm pos-btn-primary"
                        :disabled="!canValidate" @click="validateSource()">
                    <span x-show="!busy" class="inline-flex items-center gap-2">
                        {{ __('restore.source.continue') }}
                        <x-icon name="arrow-right" class="w-4 h-4" />
                    </span>
                    <span x-show="busy">…</span>
                </button>
            </div>
        </div>

        {{-- ─────────────────────────── STEP 2: REVIEW ─────────────────────────── --}}
        <div x-show="step === 'review'" x-cloak class="card">
            <div class="card-header"><div>
                <div class="card-title">{{ __('restore.review.title') }}</div>
            </div></div>
            <div class="card-body space-y-4">

                <div class="alert" :class="compatible ? 'alert-success' : 'alert-danger'">
                    <span class="alert-icon"><x-icon :name="'check'" class="w-5 h-5" x-show="compatible" /><x-icon name="alert" class="w-5 h-5" x-show="!compatible" /></span>
                    <div class="alert-body">
                        <div class="alert-title" x-text="compatible ? @js(__('restore.review.compatible')) : @js(__('restore.review.incompatible'))"></div>
                        <div class="alert-msg" x-show="!compatible" x-text="incompatibleReason"></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="field">
                        <span class="field-label">{{ __('restore.review.created') }}</span>
                        <p class="text-sm" x-text="fmtDate(summary?.created_at)"></p>
                    </div>
                    <div class="field">
                        <span class="field-label">{{ __('restore.review.company') }}</span>
                        <p class="text-sm" x-text="summary?.company || '—'"></p>
                    </div>
                    <div class="field">
                        <span class="field-label">{{ __('restore.review.app_version') }}</span>
                        <p class="text-sm mono" x-text="summary?.app_version || '—'"></p>
                    </div>
                    <div class="field">
                        <span class="field-label">{{ __('restore.review.size') }}</span>
                        <p class="text-sm tnum" x-text="fmtSize(summary?.size)"></p>
                    </div>
                </div>

                <div class="field">
                    <span class="field-label">{{ __('restore.review.contents') }}</span>
                    <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm fg-secondary">
                        <span><span class="tnum" x-text="summary?.counts?.products ?? '—'"></span> {{ __('restore.counts.products') }}</span>
                        <span><span class="tnum" x-text="summary?.counts?.customers ?? '—'"></span> {{ __('restore.counts.customers') }}</span>
                        <span><span class="tnum" x-text="summary?.counts?.sales ?? '—'"></span> {{ __('restore.counts.sales') }}</span>
                        <span><span class="tnum" x-text="summary?.counts?.users ?? '—'"></span> {{ __('restore.counts.users') }}</span>
                    </div>
                </div>

                <div class="flex justify-between">
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="backToSource()">
                        <x-icon name="back" class="w-4 h-4" />
                        {{ __('restore.review.back') }}
                    </button>
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-primary"
                            :disabled="!compatible" @click="toConfirm()">
                        {{ __('restore.review.continue') }}
                        <x-icon name="arrow-right" class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>

        {{-- ─────────────────────────── STEP 3: CONFIRM ────────────────────────── --}}
        <div x-show="step === 'confirm'" x-cloak class="card">
            <div class="card-header"><div>
                <div class="card-title">{{ __('restore.confirm.title') }}</div>
            </div></div>
            <div class="card-body space-y-4">

                <div class="alert alert-danger">
                    <span class="alert-icon"><x-icon name="alert" class="w-5 h-5" /></span>
                    <div class="alert-body">
                        <div class="alert-msg">{{ __('restore.confirm.warning') }}</div>
                    </div>
                </div>

                <label class="field-toggle">
                    <input type="checkbox" x-model="preRestore">
                    <span>{{ __('restore.confirm.pre_restore') }}</span>
                </label>

                <label class="field">
                    <span class="field-label is-required">{{ __('restore.confirm.type_prompt', ['word' => __('restore.confirm.word')]) }}</span>
                    <input type="text" class="pos-input" autocomplete="off" spellcheck="false"
                           x-model="confirmText" @keydown.enter.prevent="run()"
                           placeholder="{{ __('restore.confirm.word') }}">
                </label>

                <div class="flex justify-between">
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" @click="backToReview()">
                        <x-icon name="back" class="w-4 h-4" />
                        {{ __('restore.confirm.back') }}
                    </button>
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-danger"
                            :disabled="!canRun" @click="run()">
                        <x-icon name="refresh" class="w-4 h-4" />
                        {{ __('restore.confirm.start') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- ────────────────────── RUNNING / DONE OVERLAY ──────────────────────── --}}
        <div x-show="step === 'running' || step === 'done'" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 text-white text-center px-6">
            <div class="flex flex-col items-center gap-4 max-w-sm">
                {{-- Running --}}
                <template x-if="step === 'running'">
                    <div class="flex flex-col items-center gap-4">
                        <div class="h-12 w-12 animate-spin rounded-full border-4 border-white/25 border-t-white"></div>
                        <div class="text-lg font-semibold">{{ __('restore.running.title') }}</div>
                        <div class="text-sm text-white/70">{{ __('restore.running.do_not') }}</div>
                    </div>
                </template>
                {{-- Done --}}
                <template x-if="step === 'done'">
                    <div class="flex flex-col items-center gap-4">
                        <div class="h-12 w-12 flex items-center justify-center rounded-full bg-white/15">
                            <x-icon name="check" class="w-7 h-7" />
                        </div>
                        <div class="text-lg font-semibold">{{ __('restore.done.title') }}</div>
                        <div class="text-sm text-white/70">{{ __('restore.done.sub') }}</div>
                        <button type="button" class="pos-btn pos-btn-sm pos-btn-primary mt-2" @click="goLogin()">
                            {{ __('restore.done.login') }}
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>
</x-admin-layout>
