<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Customer statement, emailable.
 *
 * Renders the same `admin.customers.statement.print` Blade used by the
 * print surface so what the customer sees in their inbox is exactly
 * what the cashier sees on screen.
 *
 * @param array<string, mixed> $statement output of {@see \App\Services\Customers\GenerateCustomerStatement}
 */
class CustomerStatementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly array $statement) {}

    public function envelope(): Envelope
    {
        $customer = $this->statement['customer'] ?? null;
        $from     = $this->statement['from']->format('Y-m-d');
        $to       = $this->statement['to']->format('Y-m-d');

        return new Envelope(
            subject: __('customer_statement.email.subject', [
                'name' => $customer?->name ?? '',
                'from' => $from,
                'to'   => $to,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'admin.customers.statement.print',
            with: $this->statement + ['autoPrint' => false],
        );
    }
}
