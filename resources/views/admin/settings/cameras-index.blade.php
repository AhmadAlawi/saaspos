<x-admin-layout
    active="camera-settings"
    title="Cameras"
    :crumbs="[
        ['label' => __('admin.shell.brand_sub')],
        ['label' => 'Cameras'],
    ]">

    <div class="page-wide">
        <div class="page-header mb-6">
            <div>
                <h1 class="page-title">Cameras <span class="prod-badge prod-badge-muted">Beta</span></h1>
                <p class="page-sub">
                    Each branch has its own Hikvision NVR — set up the connection per store below.
                    Super admins only. Once configured, a "Camera footage" panel appears on that
                    store's invoices, letting you search and download the recording near a sale's time.
                </p>
            </div>
        </div>

        <div class="card card-pad-0">
            <div class="dt-scroll">
            <table class="dt-table">
                <thead>
                    <tr>
                        <th>Store</th>
                        <th>NVR host</th>
                        <th>Channel</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stores as $s)
                        @php $c = $configured->get($s->id); @endphp
                        <tr>
                            <td>
                                <div class="font-medium">{{ $s->name }}</div>
                                <div class="fg-tertiary text-xs mono">{{ $s->code }}</div>
                            </td>
                            <td class="mono fg-tertiary">{{ $c?->host ?? '—' }}</td>
                            <td class="fg-tertiary">{{ $c?->channel ?? '—' }}</td>
                            <td>
                                @if ($c?->is_active)
                                    <span class="prod-badge prod-badge-positive">Enabled</span>
                                @elseif ($c)
                                    <span class="prod-badge prod-badge-muted">Saved, disabled</span>
                                @else
                                    <span class="prod-badge prod-badge-muted">Not configured</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('admin.settings.cameras.edit', $s) }}" class="pos-btn pos-btn-xs pos-btn-ghost">
                                    {{ $c ? 'Edit' : 'Configure' }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    </div>
</x-admin-layout>
