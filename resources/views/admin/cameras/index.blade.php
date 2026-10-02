<x-admin-layout
    active="cameras"
    title="Cameras"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => 'Cameras'],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">Cameras <span class="prod-badge prod-badge-muted">Beta</span></h1>
                <p class="page-sub">Real live video for terminals with a camera assigned; still-frame previews (refreshed every ~1.5s) for every other channel on the NVR.</p>
            </div>
        </div>

        @if (! $nvrConfigured)
            <div class="card">
                <div class="card-body">
                    <p class="fg-tertiary">No NVR configured for this store yet — a super admin needs to set one up under Settings → Cameras first.</p>
                </div>
            </div>
        @else
            @if ($terminals->isNotEmpty())
                <script src="https://cdn.jsdelivr.net/npm/hls.js@1/dist/hls.min.js"></script>
                <div class="mb-2">
                    <div class="fg-tertiary text-xs font-medium uppercase tracking-wide mb-2">Assigned to terminals — live</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                        @foreach ($terminals as $t)
                            <div class="card" x-data="{
                                error: null,
                                keepalive: null,
                                streamUrl: {{ \Illuminate\Support\Js::from(route('admin.cameras.stream', $t)) }},
                                async load() {
                                    try {
                                        const { data } = await $http.get(this.streamUrl);
                                        const video = this.$refs.video;
                                        if (video.canPlayType('application/vnd.apple.mpegurl')) {
                                            video.src = data.url;
                                        } else if (window.Hls && window.Hls.isSupported()) {
                                            const hls = new window.Hls();
                                            hls.loadSource(data.url);
                                            hls.attachMedia(video);
                                        } else {
                                            this.error = 'This browser cannot play live video.';
                                            return;
                                        }
                                        video.play().catch(() => {});
                                        this.keepalive = setInterval(() => $http.get(this.streamUrl), 10000);
                                    } catch (e) {
                                        this.error = e?.message || 'Could not start stream.';
                                    }
                                },
                            }" x-init="load()">
                                <div class="card-body">
                                    <div class="font-medium text-sm mb-2">{{ $t->name }}</div>
                                    <div style="aspect-ratio:16/9; background:#000; border-radius:var(--radius-md,8px); overflow:hidden;">
                                        <video x-ref="video" autoplay muted playsinline controls style="width:100%; height:100%; object-fit:cover;"></video>
                                    </div>
                                    <template x-if="error">
                                        <p class="field-error mt-1 text-xs" x-text="error"></p>
                                    </template>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="fg-tertiary text-xs font-medium uppercase tracking-wide mb-2">All NVR channels</div>
            @if ($channelsError)
                <div class="card"><div class="card-body"><p class="field-error">Could not list cameras from the NVR: {{ $channelsError }}</p></div></div>
            @elseif (empty($channels))
                <div class="card"><div class="card-body"><p class="fg-tertiary">No cameras reported by the NVR.</p></div></div>
            @else
                {{-- Manual per-card start, NOT auto-start-all: this NVR has
                     dozens of channels, and polling every single one every
                     1.5s at once ties up dozens of PHP-FPM workers in a
                     repeating burst against one device — that starved the
                     rest of the app of workers the first time this ran
                     auto-start. Click "Live" on the one you actually want
                     to look at. --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"
                     x-data="{
                        srcs: {}, timers: {}, errors: {},
                        snapshotBase: {{ \Illuminate\Support\Js::from(route('admin.cameras.channel-snapshot', ['store_id' => $storeId])) }},
                        toggle(ch) {
                            if (this.timers[ch]) {
                                clearInterval(this.timers[ch]);
                                delete this.timers[ch];
                                delete this.srcs[ch];
                                return;
                            }
                            const tick = () => {
                                const u = this.snapshotBase + '&channel=' + ch + '&_=' + Date.now();
                                const img = new Image();
                                img.onload  = () => { this.srcs[ch] = u; this.errors[ch] = null; };
                                img.onerror = () => { this.errors[ch] = 'Feed unavailable.'; };
                                img.src = u;
                            };
                            tick();
                            this.timers[ch] = setInterval(tick, 1500);
                        },
                     }">
                    @foreach ($channels as $ch)
                        <div class="card">
                            <div class="card-body">
                                <div class="flex items-center justify-between mb-2">
                                    <div>
                                        <div class="font-medium text-sm">Channel {{ $ch['id'] }}</div>
                                        <div class="fg-tertiary text-xs">{{ $ch['name'] ?: '—' }}</div>
                                    </div>
                                    <button type="button" class="pos-btn pos-btn-xs pos-btn-ghost" @click="toggle({{ $ch['id'] }})">
                                        <span x-show="!timers[{{ $ch['id'] }}]">▶ Live</span>
                                        <span x-show="timers[{{ $ch['id'] }}]">■ Stop</span>
                                    </button>
                                </div>
                                <div style="aspect-ratio:16/9; background:var(--surface-container-high, #1a1a1a); border-radius:var(--radius-md,8px); overflow:hidden; display:flex; align-items:center; justify-content:center;">
                                    <img x-show="srcs[{{ $ch['id'] }}]" :src="srcs[{{ $ch['id'] }}]" alt="Channel {{ $ch['id'] }}" style="width:100%; height:100%; object-fit:cover;">
                                    <span x-show="!srcs[{{ $ch['id'] }}] && !errors[{{ $ch['id'] }}]" class="fg-tertiary text-xs">Press Live to preview</span>
                                    <span x-show="errors[{{ $ch['id'] }}]" class="fg-tertiary text-xs" x-text="errors[{{ $ch['id'] }}]"></span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</x-admin-layout>
