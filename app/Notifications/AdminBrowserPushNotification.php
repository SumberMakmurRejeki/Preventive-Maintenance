<?php

namespace App\Notifications;

use App\Models\PrimeNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class AdminBrowserPushNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected PrimeNotification $primeNotification,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->primeNotification->title)
            ->body($this->primeNotification->message)
            ->icon(url('/favicon.ico'))
            ->badge(url('/favicon.ico'))
            ->tag('prime-notification-'.$this->primeNotification->id)
            ->data($this->toArray($notifiable))
            ->options([
                'TTL' => 3600,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'notification_id' => $this->primeNotification->id,
            'notification_type' => $this->primeNotification->notification_type,
            'title' => $this->primeNotification->title,
            'message' => $this->primeNotification->message,
            'target_url' => $this->primeNotification->target_url ?: route('dashboard'),
        ];
    }
}
