@if ($found)
    <svg {{ $attributes }}
         viewBox="0 0 24 24"
         fill="none"
         stroke="currentColor"
         stroke-width="1.6"
         stroke-linecap="round"
         stroke-linejoin="round"
         aria-hidden="true">{!! $paths !!}</svg>
@endif
