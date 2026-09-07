@props([
    'route',           // index route name, e.g. 'admin.purchases.index'
])

{{--
    Reset-filters action — system-wide convention. Drop this at the end
    of every `.inv-filter` form so the user can clear every query param
    in one click. Renders as an outlined button styled to match the
    rest of the toolbar (44px, border-default, ghost-feel).

    The link points at the bare index URL (no query string), letting
    the server defaults take over. No JS — pure HTML, accessible.

    ALWAYS rendered (no "show only when filtered" gate) — per user
    feedback: the button should be a permanent affordance so users
    learn the action exists.
--}}
<a href="{{ route($route) }}"
   class="inv-filter-reset"
   title="{{ __('table.filter_reset') }}">
    <x-icon name="x" class="w-3.5 h-3.5" />
    <span>{{ __('table.filter_reset') }}</span>
</a>
