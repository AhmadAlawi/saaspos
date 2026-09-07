@props([
    'name'      => 'attachment',
    'accept'    => null,
    'maxSizeKb' => 10240,           // 10 MB default
    'hint'      => null,            // small line under the zone
])

{{--
    Styled single-file picker (PDF / image / document). Replaces the bare
    browser `<input type="file">` with a dashed drop-zone that matches the
    image-upload control: click or drag-and-drop, then the chosen file shows
    as a chip with its name + size and a clear (×) button.

    The native input stays in the DOM (visually hidden) so the file submits
    with the surrounding multipart form — no upload endpoint, no JS payload.
--}}
<div class="pos-file-drop"
     x-data="fileUpload({
        maxSizeKb: {{ (int) $maxSizeKb }},
        tooLargeMessage: @js(__('admin.file_upload.too_large')),
     })">

    <input type="file"
           name="{{ $name }}"
           @if ($accept) accept="{{ $accept }}" @endif
           x-ref="input"
           class="pos-file-drop-input"
           @change="onFileChange($event)">

    {{-- Empty state — the clickable drop-zone. --}}
    <button type="button"
            class="pos-file-drop-zone"
            x-show="!hasFile"
            :class="{ 'is-dragover': isDragOver }"
            @click="pick()"
            @dragover.prevent="isDragOver = true"
            @dragleave.prevent="isDragOver = false"
            @drop.prevent="onDrop($event)">
        <span class="pos-file-drop-icon"><x-icon name="upload" class="w-5 h-5" /></span>
        <span class="pos-file-drop-text">
            <span class="pos-file-drop-title">{{ __('admin.file_upload.title') }}</span>
            <span class="pos-file-drop-sub">{{ $hint ?? __('admin.file_upload.sub') }}</span>
        </span>
    </button>

    {{-- Selected state — filename chip + clear. --}}
    <div class="pos-file-drop-chip" x-show="hasFile" x-cloak>
        <span class="pos-file-drop-chip-icon"><x-icon name="receipt" class="w-4 h-4" /></span>
        <span class="pos-file-drop-chip-body">
            <span class="pos-file-drop-chip-name" x-text="fileName"></span>
            <span class="pos-file-drop-chip-size" x-text="fileSize"></span>
        </span>
        <button type="button" class="pos-file-drop-clear" @click="clear()"
                aria-label="{{ __('admin.file_upload.clear') }}">
            <x-icon name="x" class="w-4 h-4" />
        </button>
    </div>

    <p class="pos-file-drop-error" x-show="error" x-text="error" x-cloak></p>
</div>
