@props(['source'])

{{--
    Colour-coded journal source badge. Reuses the `.prod-badge` pill (dot +
    shape) and adds a per-source colour via `.src-<source>` (see accounting.css)
    so Sale / Purchase / Expense / payments read apart at a glance.
--}}
<span class="prod-badge src-{{ $source }}">{{ __('reports.general_ledger.sources.'.$source, [], $source) }}</span>
