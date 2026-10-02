<x-admin-layout
    active="camera-settings"
    :title="'Cameras — '.$store->name"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => 'Cameras', 'href' => route('admin.settings.cameras.index')],
        ['label' => $store->name],
    ]">

    <div class="page-wide" style="max-width: 640px;">
        <div class="page-header mb-6">
            <div class="flex items-start gap-3">
                <a href="{{ route('admin.settings.cameras.index') }}" class="pos-btn pos-btn-sm pos-btn-ghost" aria-label="Cameras">
                    <x-icon name="back" class="w-4 h-4" />
                </a>
                <div>
                    <h1 class="page-title">{{ $store->name }} <span class="prod-badge prod-badge-muted">Beta</span></h1>
                    <p class="page-sub">This store's Hikvision NVR — its own device on its own network, separate from every other branch's.</p>
                </div>
            </div>
        </div>

        <div class="card" x-data="{
            testing: false,
            testResult: null,
            testOk: null,
            test() {
                this.testing = true; this.testResult = null;
                $http.post({{ \Illuminate\Support\Js::from(route('admin.settings.cameras.test', $store)) }}, {})
                    .then(({ data }) => { this.testOk = true; this.testResult = data.message; })
                    .catch((e) => { this.testOk = false; this.testResult = e?.message || 'Connection failed.'; })
                    .finally(() => { this.testing = false; });
            },
        }">
            <div class="card-header"><div>
                <div class="card-title">NVR connection</div>
            </div></div>
            <form method="POST" action="{{ route('admin.settings.cameras.update', $store) }}" data-ajax-form class="card-body">
                @csrf
                <div class="form-stack">
                    <div class="grid grid-cols-1 sm:grid-cols-[minmax(0,1fr)_120px] gap-3">
                        <label class="field">
                            <span class="field-label is-required">Host / IP</span>
                            <input type="text" name="host" value="{{ old('host', $settings->host) }}" class="pos-input mono" placeholder="203.0.113.10">
                            @error('host')<p class="field-error">{{ $message }}</p>@enderror
                        </label>
                        <label class="field">
                            <span class="field-label is-required">Port</span>
                            <input type="number" name="port" min="1" max="65535" value="{{ old('port', $settings->port ?? 80) }}" class="pos-input num tnum">
                            @error('port')<p class="field-error">{{ $message }}</p>@enderror
                        </label>
                    </div>

                    <label class="field-toggle">
                        <input type="hidden" name="use_https" value="0">
                        <input type="checkbox" name="use_https" value="1" @checked(old('use_https', $settings->use_https))>
                        <span>
                            <span class="block">Use HTTPS</span>
                            <span class="block text-[11.5px] fg-tertiary mt-0.5">Off = plain http:// (default on most NVR setups unless you've configured a certificate).</span>
                        </span>
                    </label>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="field">
                            <span class="field-label is-required">Username</span>
                            <input type="text" name="username" value="{{ old('username', $settings->username) }}" class="pos-input" autocomplete="off">
                            @error('username')<p class="field-error">{{ $message }}</p>@enderror
                        </label>
                        <label class="field">
                            <span class="field-label">Password</span>
                            <input type="password" name="password" class="pos-input" autocomplete="new-password" placeholder="{{ $settings->exists ? 'Leave blank to keep current' : '' }}">
                            @error('password')<p class="field-error">{{ $message }}</p>@enderror
                        </label>
                    </div>

                    <label class="field">
                        <span class="field-label is-required">Camera channel</span>
                        <input type="number" name="channel" min="1" max="999" value="{{ old('channel', $settings->channel) }}" class="pos-input num tnum">
                        <p class="field-help">The NVR channel number covering this store's till. Check the NVR's own web UI (Configuration → Camera Management) if unsure — channel names are often generic ("Camera 01") and don't self-identify.</p>
                        @error('channel')<p class="field-error">{{ $message }}</p>@enderror
                    </label>

                    <label class="field-toggle">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $settings->is_active))>
                        <span>
                            <span class="block">Enabled</span>
                            <span class="block text-[11.5px] fg-tertiary mt-0.5">Off hides the footage panel on this store's invoices.</span>
                        </span>
                    </label>
                </div>

                <div class="flex items-center gap-3 mt-4 pt-4 border-t border-subtle">
                    <button type="submit" class="pos-btn pos-btn-sm pos-btn-primary">Save</button>
                    <button type="button" class="pos-btn pos-btn-sm pos-btn-ghost" :disabled="testing" @click="test()">
                        <span x-show="!testing">Test connection</span>
                        <span x-show="testing">Testing…</span>
                    </button>
                    <span x-show="testResult" :class="testOk ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'" class="text-xs" x-text="testResult"></span>
                </div>
            </form>
        </div>

        {{-- Cameras (beta) — every channel the NVR reports, each with a
             live-refreshing still frame (ISAPI's snapshot endpoint, not
             continuous video — Hikvision recordings are an RTSP/
             proprietary feed a browser can't play directly, and
             transcoding that into something it can is a much bigger
             build than "which camera is this" needs) and a dropdown to
             assign it to one of this store's terminals. Channel names
             from the NVR are usually generic ("Camera 01") — that's the
             whole reason to look at the picture before assigning it. --}}
        <div class="card mt-5">
            <div class="card-header"><div>
                <div class="card-title">Cameras <span class="prod-badge prod-badge-muted">Beta</span></div>
                <div class="card-title-sub">Requires the connection above to be saved and reachable first.</div>
            </div></div>
            <div class="card-body">
                @if ($channelsError)
                    <p class="field-error">Could not list cameras from the NVR: {{ $channelsError }}</p>
                @elseif (empty($channels))
                    <p class="fg-tertiary">No cameras reported yet — save a working connection above, then reload this page.</p>
                @elseif ($terminals->isEmpty())
                    <p class="fg-tertiary">This store has no terminals yet — add one under Terminals before assigning cameras.</p>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"
                         x-data="{
                            running: {},
                            srcs: {},
                            timers: {},
                            snapshotBase: {{ \Illuminate\Support\Js::from(route('admin.settings.cameras.snapshot', $store)) }},
                            toggle(ch) {
                                if (this.running[ch]) {
                                    clearInterval(this.timers[ch]);
                                    delete this.running[ch];
                                    return;
                                }
                                this.running[ch] = true;
                                const tick = () => {
                                    const url = this.snapshotBase + '?channel=' + ch + '&_=' + Date.now();
                                    const img = new Image();
                                    img.onload = () => { this.srcs[ch] = url; };
                                    img.src = url;
                                };
                                tick();
                                this.timers[ch] = setInterval(tick, 1500);
                            },
                         }">
                        @foreach ($channels as $ch)
                            @php $assignedTerminal = $terminalByChannel->get($ch['id']); @endphp
                            <div class="card">
                                <div class="card-body">
                                    <div class="flex items-center justify-between mb-2">
                                        <div>
                                            <div class="font-medium text-sm">Channel {{ $ch['id'] }}</div>
                                            <div class="fg-tertiary text-xs">{{ $ch['name'] ?: '—' }}</div>
                                        </div>
                                        <button type="button" class="pos-btn pos-btn-xs pos-btn-ghost" @click="toggle({{ $ch['id'] }})">
                                            <span x-show="!running[{{ $ch['id'] }}]">▶ Live</span>
                                            <span x-show="running[{{ $ch['id'] }}]">■ Stop</span>
                                        </button>
                                    </div>
                                    <div style="aspect-ratio:16/9; background:var(--surface-container-high, #1a1a1a); border-radius:var(--radius-md,8px); overflow:hidden; display:flex; align-items:center; justify-content:center;">
                                        <img x-show="srcs[{{ $ch['id'] }}]" :src="srcs[{{ $ch['id'] }}]" alt="Channel {{ $ch['id'] }}" style="width:100%; height:100%; object-fit:cover;">
                                        <span x-show="!srcs[{{ $ch['id'] }}]" class="fg-tertiary text-xs">Press Live to preview</span>
                                    </div>
                                    <label class="field mt-3"
                                           x-data="{
                                               busy: false,
                                               assignUrls: {{ \Illuminate\Support\Js::from($terminals->mapWithKeys(fn ($t) => [$t->id => route('admin.settings.cameras.assign', [$store, $t])])) }},
                                           }">
                                        <span class="field-label">Assigned to</span>
                                        <select class="pos-input"
                                                :disabled="busy"
                                                @change="
                                                    if (! $event.target.value) return;
                                                    busy = true;
                                                    $http.post(assignUrls[$event.target.value], { channel: {{ $ch['id'] }} })
                                                        .then(({ data }) => $store.toasts?.push({ type: 'success', message: data.message }))
                                                        .catch((e) => $store.toasts?.push({ type: 'error', message: e?.message || 'Could not save.' }))
                                                        .finally(() => { busy = false; });
                                                ">
                                            <option value="">— None —</option>
                                            @foreach ($terminals as $t)
                                                <option value="{{ $t->id }}" @selected($assignedTerminal?->id === $t->id)>
                                                    {{ $t->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-admin-layout>
