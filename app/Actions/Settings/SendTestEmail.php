<?php

namespace App\Actions\Settings;

use Illuminate\Support\Facades\Mail;

/**
 * Sends a quick, synchronous test email so the user can verify their SMTP
 * credentials work right from the Settings page. Returns an error string
 * on failure (so the controller can flash it) or `null` on success.
 *
 * Synchronous — runs in the same request as the click, never queued.
 */
class SendTestEmail
{
    public function __invoke(string $to): ?string
    {
        try {
            Mail::raw(
                __('settings.email.test.body', ['app' => config('app.name')]),
                function ($message) use ($to) {
                    $message->to($to)->subject(__('settings.email.test.subject', ['app' => config('app.name')]));
                }
            );
        } catch (\Throwable $e) {
            return $e->getMessage();
        }

        return null;
    }
}
