@props([
    'name'         => 'image',
    'initialUrl'   => null,
    'accept'       => 'image/*',
    'maxSizeKb'    => 1024,
    'emptyTitle'   => null,
    'emptySub'     => null,
    'replaceLabel' => null,
    'removeLabel'  => null,
    'variant'      => 'drop', // 'drop' (full dashed zone) | 'inline' (140px tile + side info, matches design-system/admin/Product.html)
    'tileLabel'    => null,   // inline-only: heading next to the tile
    'tileHint'     => null,   // inline-only: small hint line under the heading
    'uploadLabel'  => null,   // inline-only: button label when empty
])

{{--
    Single-image upload field.

    Props:
      name        form-field name for the file input (e.g. "logo").
                  A companion hidden input named "{name}_remove" is also
                  emitted; it's "1" when the user clicks Remove, "0"
                  otherwise. The server reads both to decide between
                  "store new", "remove existing", or "leave alone".
      initialUrl  pre-existing image URL — typically Storage::url($model->logo_path).
      accept      <input accept="..."> filter (default: image/*).
      maxSizeKb   client-side max in KB (server validates again).
      emptyTitle  / emptySub   hint text for the empty state.
      replaceLabel / removeLabel   action button labels.

    Submits as part of the surrounding <form enctype="multipart/form-data">.
    No separate upload endpoint; the file rides along with the rest of the
    form payload exactly like any other input.

    Reactivity: bind the parent's logo state via @input listeners if the
    parent needs to know about changes (e.g. to clear validation errors).
    For most editors the parent doesn't need to know — the file goes to
    the server with everything else.
--}}
<div class="img-upload {{ $variant === 'inline' ? 'is-inline' : '' }}"
     x-data="imageUpload({
        initialUrl: @js($initialUrl),
        maxSizeKb:  {{ (int) $maxSizeKb }},
        accept:     @js($accept),
     })">

    {{-- File input. `name` + `accept` are rendered server-side (not
         Alpine-bound) so the attributes exist on first paint — even
         if Alpine boots late, the file still submits correctly. --}}
    <input type="file"
           name="{{ $name }}"
           accept="{{ $accept }}"
           x-ref="fileInput"
           class="img-upload-input"
           @change="onFileChange($event)">

    {{-- Server reads this to decide between "store new" / "remove existing" / "leave alone".
         "1" only when the user explicitly clicked Remove. --}}
    <input type="hidden" name="{{ $name }}_remove" :value="removed ? '1' : '0'">

    @if ($variant === 'inline')
        <div class="img-upload-inline">
            <div class="img-upload-tile"
                 :class="{ 'is-empty': !previewUrl, 'is-dragover': isDragOver }"
                 role="button"
                 tabindex="0"
                 @click="pick()"
                 @keydown.enter.prevent="pick()"
                 @keydown.space.prevent="pick()"
                 @dragover.prevent="isDragOver = true"
                 @dragleave.prevent="isDragOver = false"
                 @drop.prevent="onDrop($event)">

                <img x-show="previewUrl"
                     :src="previewUrl"
                     alt=""
                     class="img-upload-preview"
                     @@error="previewUrl = null">

                <span x-show="!previewUrl" class="img-upload-tile-ph" aria-hidden="true">
                    <x-icon name="image" class="w-6 h-6" />
                </span>
            </div>

            <div class="img-upload-inline-body">
                <div class="img-upload-inline-title">
                    {{ $tileLabel ?? __('admin.image_upload.title') }}
                </div>
                <div class="img-upload-inline-hint">
                    {{ $tileHint ?? __('admin.image_upload.sub', ['size' => $maxSizeKb]) }}
                </div>
                <div class="img-upload-inline-actions">
                    <button type="button" class="pos-btn pos-btn-sm" @click="pick()">
                        <span x-text="previewUrl
                            ? @js($replaceLabel ?? __('admin.image_upload.replace'))
                            : @js($uploadLabel ?? __('admin.image_upload.upload'))"></span>
                    </button>
                    <button type="button"
                            class="pos-btn pos-btn-sm pos-btn-ghost"
                            x-show="previewUrl"
                            x-cloak
                            @click="remove()">
                        {{ $removeLabel ?? __('admin.image_upload.remove') }}
                    </button>
                </div>
                <p class="img-upload-error" x-show="error" x-text="error" x-cloak></p>
            </div>
        </div>
    @else
        <div class="img-upload-zone"
             :class="{ 'is-empty': !previewUrl, 'is-dragover': isDragOver }"
             role="button"
             tabindex="0"
             @click="pick()"
             @keydown.enter.prevent="pick()"
             @keydown.space.prevent="pick()"
             @dragover.prevent="isDragOver = true"
             @dragleave.prevent="isDragOver = false"
             @drop.prevent="onDrop($event)">

            <img x-show="previewUrl"
                 :src="previewUrl"
                 alt=""
                 class="img-upload-preview"
                 @@error="previewUrl = null">

            <div class="img-upload-empty" x-show="!previewUrl">
                <span class="img-upload-empty-icon">
                    <x-icon name="download" class="w-4 h-4" />
                </span>
                <span class="img-upload-empty-title">
                    {{ $emptyTitle ?? __('admin.image_upload.title') }}
                </span>
                <span class="img-upload-empty-sub">
                    {{ $emptySub ?? __('admin.image_upload.sub', ['size' => $maxSizeKb]) }}
                </span>
            </div>
        </div>

        <div class="img-upload-actions" x-show="previewUrl" x-cloak>
            <button type="button" class="img-upload-action" @click="pick()">
                <x-icon name="edit" class="w-3 h-3" />
                <span>{{ $replaceLabel ?? __('admin.image_upload.replace') }}</span>
            </button>
            <button type="button" class="img-upload-action is-danger" @click="remove()">
                <x-icon name="trash" class="w-3 h-3" />
                <span>{{ $removeLabel ?? __('admin.image_upload.remove') }}</span>
            </button>
        </div>

        <p class="img-upload-error" x-show="error" x-text="error" x-cloak></p>
    @endif
</div>
