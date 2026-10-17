<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One generic in-app alert. `key` makes it unique so the same reminder is
 * never sent twice (see GoalAlerts::notifyOnce). Email is only added when the
 * user opted in AND a real mailer is configured.
 */
class SmartSaveAlert extends Notification
{
    use Queueable;

    public function __construct(
        public string $kind,      // deadline | monthly_target | milestone | budget | inactive_goal | goal_completed
        public string $title,
        public string $message,
        public string $key,
        public ?string $url = null,
        public string $tone = 'info', // info | success | warning
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if ($notifiable->wantsNotification('email') && ! in_array(config('mail.default'), ['log', 'array', null], true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'message' => $this->message,
            'key' => $this->key,
            'url' => $this->url,
            'tone' => $this->tone,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject('SmartSave: '.$this->title)->line($this->message);

        return $this->url ? $mail->action('Open SmartSave', $this->url) : $mail;
    }
}
