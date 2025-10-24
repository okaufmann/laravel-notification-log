<?php

namespace Okaufmann\LaravelNotificationLog\Tests\Support;

use Illuminate\Notifications\Notification;
use Okaufmann\LaravelNotificationLog\Contracts\ResolveMessageForLogging;
use Okaufmann\LaravelNotificationLog\Contracts\ShouldLogNotification;
use Okaufmann\LaravelNotificationLog\Models\Concerns\LogsNotifications;

class DummyNotificationWithResolveMessage extends Notification implements ResolveMessageForLogging, ShouldLogNotification
{
    use LogsNotifications;

    public function __construct()
    {
        $this->id = 'custom-resolve-message-test';
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'message' => 'This is a custom resolved message for logging.',
        ];
    }

    public function resolveMessageForLogging(mixed $channel, $notifiable): string
    {
        $channelName = get_class($channel);
        $notifiableName = get_class($notifiable);

        return "Custom message for {$channelName} channel sent to {$notifiableName}";
    }
}
