@props([
    'placeholder' => null,
    'cameraTitle' => null,
    'cameraHint'  => null,
    'cameraLabel' => null,
])

{{--
    Shared barcode scan field for the line editors that accept scanned items —
    the inventory ones (adjustment, transfer, take) and the purchase order form.
    Renders the scan input (icon + F8 hint + spinner) + a camera button, and the
    camera scanner overlay.

    The default labels come from `inventory.scan.*`; hosts outside inventory pass
    their own via the props above.

    Drives — and is driven by — the host Alpine component, which must expose the
    `barcodeScanMixin` surface: `scanQuery`, `scanning`, `cameraSupported`,
    `cameraOpen`, `onScanEnter()`, `openCamera()`, `closeCamera()`, and the
    `scanInput` + `cameraVideo` refs. See resources/js/admin/barcode-scan-mixin.js.

    A USB/Bluetooth-HID/keyboard-wedge scanner types into the focused input and
    sends Enter; F8 (bound in the host's init, same key as the cashier screen)
    focuses it; the camera is for tablets/phones.
--}}
<div class="inv-scan">
    <div class="inv-scan-field">
        <span class="inv-scan-icon"><x-icon name="barcode" class="w-4 h-4" /></span>
        <input type="text"
               x-ref="scanInput"
               x-model="scanQuery"
               @keydown.enter.prevent="onScanEnter()"
               @keydown.escape="scanQuery = ''"
               class="pos-input inv-scan-input"
               inputmode="none"
               autocomplete="off"
               placeholder="{{ $placeholder ?? __('inventory.scan.placeholder') }}">
        <span class="inv-scan-spin" x-show="scanning" x-cloak>
            <span class="inline-flex animate-spin"><x-icon name="refresh" class="w-4 h-4" /></span>
        </span>
        <kbd class="inv-scan-kbd" x-show="!scanning" aria-hidden="true">F8</kbd>
    </div>
    <button type="button"
            class="inv-scan-cam"
            x-show="cameraSupported"
            x-cloak
            @click="openCamera()"
            :title="@js($cameraLabel ?? __('inventory.scan.camera'))"
            aria-label="{{ $cameraLabel ?? __('inventory.scan.camera') }}">
        <x-icon name="camera" class="w-5 h-5" />
    </button>

    {{-- Camera scanner overlay. Opened from the camera button; stays open so
         several items can be scanned in a row. --}}
    <div class="inv-cam-overlay" x-show="cameraOpen" x-cloak
         @keydown.escape.window="closeCamera()">
        <div class="inv-cam-modal">
            <div class="inv-cam-head">
                <span>{{ $cameraTitle ?? __('inventory.scan.camera_title') }}</span>
                <button type="button" class="inv-cam-close" @click="closeCamera()"
                        aria-label="{{ __('inventory.scan.camera_close') }}">
                    <x-icon name="x" class="w-4 h-4" />
                </button>
            </div>
            <video x-ref="cameraVideo" class="inv-cam-video" muted playsinline></video>
            <div class="inv-cam-hint">{{ $cameraHint ?? __('inventory.scan.camera_hint') }}</div>
        </div>
    </div>
</div>
