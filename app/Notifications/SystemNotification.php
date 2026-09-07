<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * A single generic database notification. Rather than a class per event, every
 * admin notification flows through this one — the distinguishing detail lives
 * in the `data` payload (key / title / message / icon / url), which the topbar
 * bell dropdown renders directly. New triggers just call `notify_admins(...)`.
 */
class SystemNotification extends Notification
{
    /** @param array<string,mixed> $payload */
    public function __construct(private readonly array $payload)
    {
    }

    /** @return array<int,string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string,mixed> */
    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }
}
