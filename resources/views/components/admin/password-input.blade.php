{{--
    Password input with a show/hide eye toggle.

    Mirrors the `.pos-input-wrap.has-suffix` pattern (same as the login
    screen). Pass-through attributes land on the <input>, so usage matches
    a plain `.pos-input`:

        <x-admin.password-input name="password" autocomplete="new-password" />

    Don't pass `type` — it's bound to the toggle. Wrap in a `<label
    class="field">` with the field-label + error, like any other input.
--}}
<div class="pos-input-wrap has-suffix" x-data="{ shown: false }">
    <input x-bind:type="shown ? 'text' : 'password'"
           {{ $attributes->merge(['class' => 'pos-input']) }}>
    <button type="button"
            class="pos-input-suffix"
            tabindex="-1"
            @click="shown = !shown"
            :aria-label="shown ? @js(__('auth.hide_password')) : @js(__('auth.show_password'))">
        <span x-show="!shown"><x-icon name="eye" class="w-[18px] h-[18px]" /></span>
        <span x-show="shown" x-cloak><x-icon name="eye-off" class="w-[18px] h-[18px]" /></span>
    </button>
</div>
