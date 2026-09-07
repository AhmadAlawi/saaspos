{{-- Read-only banner shown at the top of settings pages that are locked on
     the public demo. Renders nothing on a real install. Pair it with a
     `<fieldset @disabled(pos_is_demo())>` around the form so the fields
     themselves are also disabled; the controller blocks the write too. --}}
@if (pos_is_demo())
    <div class="alert alert-warning mb-5">
        <span class="alert-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.86a2 2 0 0 1 3.4 0l8.39 14.6A2 2 0 0 1 20.39 22H3.61a2 2 0 0 1-1.7-3.54z"/></svg>
        </span>
        <div class="alert-body"><p class="alert-msg">{{ $slot->isEmpty() ? __('settings.demo.locked_banner') : $slot }}</p></div>
    </div>
@endif
