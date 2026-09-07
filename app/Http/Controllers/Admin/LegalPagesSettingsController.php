<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Privacy policy + terms of service editor.
 *
 * Some payment gateways (Razorpay, Paystack, Flutterwave, etc.) require
 * a public privacy policy and terms-of-service URL during onboarding;
 * this gives the merchant a place to author them and a stable public
 * URL to point at. Content is stored as markdown on the company row and
 * rendered to HTML on the public-facing routes.
 */
class LegalPagesSettingsController extends Controller
{
    use RespondsJsonOrRedirect;

    public function edit(): View
    {
        $this->authorize('settings.view');

        return view('admin.settings.legal-pages', [
            'company' => Company::current() ?? new Company(),
        ]);
    }

    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorize('settings.update');

        if (pos_is_demo()) {
            return $this->jsonOrError(
                $request,
                __('settings.demo.locked'),
                route('admin.settings.legal-pages.edit'),
            );
        }

        $data = $request->validate([
            // 64 KB caps — plenty for a policy / TOS and a hard stop
            // before a stray paste blows up the row size.
            'privacy_policy'   => ['nullable', 'string', 'max:65535'],
            'terms_of_service' => ['nullable', 'string', 'max:65535'],
        ]);

        $company = Company::current();
        abort_unless($company !== null, 404);

        // No `updated_by` here — `company` doesn't carry that column
        // (unlike sales / purchases / etc.). Matches what every other
        // settings controller does.
        $company->forceFill([
            'privacy_policy'   => $this->sanitize($data['privacy_policy']   ?? null),
            'terms_of_service' => $this->sanitize($data['terms_of_service'] ?? null),
        ])->save();

        return $this->jsonOrRedirect(
            $request,
            __('settings.legal_pages.flash.saved'),
            route('admin.settings.legal-pages.edit'),
        );
    }

    /**
     * Strip everything except a small allowlist of formatting tags
     * the HugeRTE toolbar can produce: p / br, strong / em / s / u /
     * b / i, h1-h4, ul / ol / li, blockquote, a. `strip_tags()` then
     * nukes any `<script>`, `<iframe>`, on-handlers or `javascript:`
     * URLs that survived (the latter via an extra regex pass on
     * href / src attributes).
     *
     * The rendered output is safe to emit with `{!! ... !!}` on the
     * public page.
     */
    private function sanitize(?string $html): ?string
    {
        if ($html === null) return null;
        $trimmed = trim($html);
        // Treat an editor "empty" doc as null so the public 404 still
        // fires after the user clears it. HugeRTE can emit a few
        // different shapes for "the user typed nothing" — covers the
        // bare placeholder paragraph and the &nbsp; variants.
        $stripped = trim(strip_tags(str_replace(['&nbsp;', "\xC2\xA0"], ' ', $trimmed)));
        if ($stripped === '') return null;

        // `<b>` / `<i>` allowed too — HugeRTE itself emits <strong>/<em>,
        // but paste-from-Word/Google-Docs sometimes leaves <b>/<i>, and
        // they're equally safe. `<u>` covers the Ctrl+U shortcut.
        $allowed = '<p><br><strong><em><s><u><b><i><h1><h2><h3><h4><ul><ol><li><blockquote><a>';
        $clean   = strip_tags($trimmed, $allowed);

        // Belt-and-suspenders: scrub javascript: URLs from href / src
        // attributes that survived strip_tags.
        $clean = preg_replace('#(href|src)\s*=\s*"\s*javascript:[^"]*"#i', '$1="#"', $clean);
        $clean = preg_replace("#(href|src)\s*=\s*'\s*javascript:[^']*'#i", "$1='#'", $clean);
        // And any `on*=` event handlers that might have ridden along
        // on the allowed tags (TipTap doesn't emit them, but paste-
        // from-Word might).
        $clean = preg_replace('#\son\w+\s*=\s*"[^"]*"#i', '', $clean);
        $clean = preg_replace("#\son\w+\s*=\s*'[^']*'#i", '', $clean);

        return $clean;
    }
}
