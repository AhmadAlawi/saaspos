@extends('installer.layout')

@section('content')
    <h2 class="text-[22px] font-semibold fg-primary tracking-[-0.01em]">{{ __('installer.welcome.heading') }}</h2>
    <p class="mt-2 text-[13.5px] fg-secondary leading-relaxed">{{ __('installer.welcome.subheading') }}</p>

    <form method="POST" action="{{ route('install.language') }}" class="mt-8 space-y-6"
          x-data="{ submitting: false }" @submit="submitting = true">
        @csrf

        <label class="block">
            <span class="field-label">{{ __('installer.welcome.language_label') }}</span>
            <select name="language" required class="pos-input" x-data="enhancedSelect()">
                @foreach ($languages as $code => $label)
                    <option value="{{ $code }}" @selected($selectedLanguage === $code)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <div class="flex justify-end">
            <x-installer.submit-button>{{ __('installer.continue') }}</x-installer.submit-button>
        </div>
    </form>
@endsection
