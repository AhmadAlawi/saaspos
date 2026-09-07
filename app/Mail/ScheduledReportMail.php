<?php

namespace App\Mail;

use App\Models\ScheduledReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Delivers a scheduled report to a recipient with the generated file
 * attached. Subject/body fall back to sensible defaults but honour the
 * schedule's own templates (with {report} + {date} placeholders) when set.
 *
 * Sent synchronously (see {@see \App\Actions\Reports\RunScheduledReport}) so
 * it works on shared hosting without a queue worker.
 */
class ScheduledReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ScheduledReport $schedule,
        public readonly string $attachPath,
        public readonly string $reportTitle,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->fillTemplate($this->schedule->subject_template)
            ?: __('reports.schedule.email.subject', [
                'report' => $this->reportTitle,
                'app'    => config('app.name'),
            ]);

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.scheduled-report',
            with: [
                'appName'     => config('app.name'),
                'reportTitle' => $this->reportTitle,
                'scheduleName' => $this->schedule->name,
                'body'        => $this->fillTemplate($this->schedule->message_template),
                'filename'    => basename($this->attachPath),
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->attachPath)
                ->as(basename($this->attachPath))
                ->withMime($this->mimeFor($this->attachPath)),
        ];
    }

    /** Replace {report} + {date} placeholders; returns null for a blank template. */
    private function fillTemplate(?string $template): ?string
    {
        $template = trim((string) $template);
        if ($template === '') {
            return null;
        }

        return strtr($template, [
            '{report}' => $this->reportTitle,
            '{date}'   => now()->format('Y-m-d'),
        ]);
    }

    private function mimeFor(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf'  => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'text/csv',
        };
    }
}
