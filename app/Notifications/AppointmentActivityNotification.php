<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class AppointmentActivityNotification extends Notification
{
    public function __construct(
        public string $message,
        public string $url,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{message: string, url: string}
     */
    public function toDatabase(object $notifiable): array
    {
        return ['message' => $this->message, 'url' => $this->url];
    }
}
