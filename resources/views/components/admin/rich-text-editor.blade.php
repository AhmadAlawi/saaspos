@props([
    'name',                  // form field name → goes on the hidden input
    'initial' => '',         // initial HTML content
])

{{--
    Rich-text editor — HugeRTE-backed.

    See resources/js/admin/rich-text-editor.js for the host factory. The
    factory replaces the textarea below with HugeRTE on mount and writes
    the editor's HTML into the sibling hidden input on every change, so
    the form submit picks it up transparently.

    Why a textarea here (and not a `<div>` mount point as with the old
    TipTap setup): HugeRTE follows TinyMCE's textarea-takeover model and
    falls back gracefully to the plain textarea when the script fails to
    load. The textarea is the form value when the editor isn't ready,
    so an extremely-early submit still ships the initial content.
--}}
<div class="rte" x-data="richTextEditor({ initial: @js((string) $initial) })">
    {{-- The textarea is visible from page load so the user always
         sees a working editing surface. HugeRTE replaces it with its
         own UI once the runtime finishes loading. If HugeRTE fails to
         fetch (network / 404), the bare textarea stays and the save
         button still works — graceful degradation. --}}
    <textarea x-ref="ta" class="rte-textarea">{{ $initial }}</textarea>

    {{-- Hidden input that carries the editor's HTML on submit. The
         factory keeps it in sync with the editor on every keystroke. --}}
    <input type="hidden" name="{{ $name }}" x-ref="hidden" value="{{ $initial }}">
</div>
