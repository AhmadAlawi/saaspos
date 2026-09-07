<?php

namespace App\Actions\Settings;

use App\Mail\BackupCompletedMail;
use App\Models\BackupLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Emails a copy of a completed backup to the company email (opt-in via
 * Settings → Backup → "Email a copy …"). Attaches the archive when it's small
 * enough for SMTP; otherwise sends a notice with the size and a reminder to
 * download it from the panel.
 *
 * Best-effort: never throws — a mail failure must not fail the backup that
 * triggered it. Called after both the scheduled run and the manual "Run now".
 */
class EmailBackupCopy
{
    /** Hard ceiling for attaching — above this we send a notice instead. */
    public const MAX_ATTACH_BYTES = 15 * 1024 * 1024; // 15 MB

    public function __invoke(BackupLog $log): void
    {
        if (! app_backup()['email_enabled']) {
            return;
        }
        if ($log->status !== 'success' || ! $log->file_path) {
            return;
        }

        $to = $this->recipient();
        if ($to === null) {
            return;
        }

        $diskName = array_key_exists((string) $log->destination, config('filesystems.disks', []))
            ? (string) $log->destination
            : 'local';
        $abs = Storage::disk($diskName)->path($log->file_path);
        if (! is_file($abs)) {
            return;
        }

        $size   = (int) ($log->file_size_bytes ?: filesize($abs) ?: 0);
        $attach = $size > 0 && $size <= self::MAX_ATTACH_BYTES;

        try {
            Mail::to($to)->send(new BackupCompletedMail(
                $log,
                $attach ? $abs : null,
                $this->humanSize($size),
            ));
        } catch (\Throwable $e) {
            // Never let a mail problem break the backup flow.
            report($e);
        }
    }

    /** Company profile email, falling back to the first super-admin's email. */
    private function recipient(): ?string
    {
        $email = Company::current()?->email;
        if ($email) {
            return $email;
        }

        return User::query()->where('is_super_admin', true)->value('email');
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
