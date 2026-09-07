<form method="POST" action="{{ route('admin.locale.switch', $target->code) }}" class="contents">
    @csrf
    <button type="submit"
            class="{{ $buttonClass }}"
            title="{{ __('languages.switcher.heading') }}: {{ $target->native_name }}"
            aria-label="{{ __('languages.switcher.heading') }}: {{ $target->native_name }}">
        <span class="lang-toggle-code">{{ \Illuminate\Support\Str::upper($target->code) }}</span>
    </button>
</form>
