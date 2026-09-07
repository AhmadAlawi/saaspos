{{--
    Google Analytics 4 tag for the public DEMO install ONLY.

    Included from every layout <head> (landing, admin, cashier, auth, installer)
    so the demo can track visitor movement across the whole product — not just
    the marketing page. It self-gates on demo mode + a configured Measurement ID,
    so it is impossible for it to load on a real customer install (which must
    never phone home — see CLAUDE.md §2). A real install has POS_DEMO_MODE off,
    so `pos_is_demo()` is false and this renders nothing.

    The inline gtag snippet is the vendor-supplied boilerplate; it is the one
    sanctioned place we hand-write a <script> in a Blade file, because GA has to
    be inlined in the document head to capture the initial page view.
--}}
@php
    $gaId = pos_is_demo() ? trim((string) config('pos.demo.analytics_id')) : '';
@endphp
@if ($gaId !== '')
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', {!! Js::from($gaId) !!});
    </script>
@endif
