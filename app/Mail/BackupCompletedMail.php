<?php

namespace App\Mail;

use App\Models\BackupLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifies the company email that a backup completed, optionally attaching the
 * archive itself. The archive is attached only when small enough (the caller —
 * {@see \App\Actions\Settings\EmailBackupCopy} — passes a path or null); a
 * too-large backup still sends, as a plain "download it from the panel" notice,
 * because silently bouncing on SMTP attachment limits would be worse.
 *
 * Sent synchronously (not queued) so it works on shared hosting without a queue
 * worker — see EmailBackupCopy.
 */
class BackupCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly BackupLog $log,
        public readonly ?string $attachPath,
        public readonly string $sizeLabel,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('settings.backup.email.subject', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.backup-completed',
            with: [
                'log'       => $this->log,
                'attached'  => $this->attachPath !== null,
                'sizeLabel' => $this->sizeLabel,
                'filename'  => basename((string) $this->log->file_path),
                'appName'   => config('app.name'),
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        if ($this->attachPath === null) {
            return [];
        }

        return [
            Attachment::fromPath($this->attachPath)
                ->as(basename($this->attachPath))
                ->withMime('application/zip'),
        ];
    }
}
