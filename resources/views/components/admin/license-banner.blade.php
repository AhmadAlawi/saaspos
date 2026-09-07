{{--
    Non-blocking license banner (docs/features/installer.md §6.3).

    Renders NOTHING unless panel-side license verification is switched on
    (config/pos.php → license.recheck, off by default). Without that gate a
    stale `license_status = invalid` — e.g. a fresh install whose one-time
    code check didn't land — nagged on every admin page forever, with no way
    to clear it.

    When enabled it shows ONLY when the license needs attention: status is
    `invalid`, or a recent re-check couldn't reach the server (`last_error`).
    It never blocks anything: a slim notice with a link to the License page,
    shown only to operators who can act on it (settings viewers /
    super-admins) so cashiers aren't alarmed by something they can't fix.
--}}
@php
    $licenseChecksOn = (bool) config('pos.license.recheck');
    $lic  = $licenseChecksOn ? app_license() : [];
    $user = auth()->user();
    $canSee = $user && ($user->is_super_admin || $user->hasPermission('settings.view'));
    $isInvalid = ($lic['status'] ?? null) === 'invalid';
    $needsAttention = $isInvalid || ! empty($lic['last_error']);
@endphp

@if ($licenseChecksOn && $canSee && $needsAttention)
    <div class="license-banner {{ $isInvalid ? 'is-invalid' : 'is-warning' }}" role="alert">
        <x-icon name="alert" class="w-4 h-4 shrink-0" />
        <span class="license-banner-text">
            {{ $isInvalid ? __('admin.license_banner.invalid') : __('admin.license_banner.unverified') }}
        </span>
        <a href="{{ route('admin.settings.license.index') }}" class="license-banner-link">
            {{ __('admin.license_banner.action') }}
        </a>
    </div>
@endif
