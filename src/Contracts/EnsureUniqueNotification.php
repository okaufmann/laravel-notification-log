<?php

namespace Okaufmann\LaravelNotificationLog\Contracts;

use Okaufmann\LaravelNotificationLog\Database\NotificationHistoryQueryBuilder;

interface EnsureUniqueNotification
{
    public function wasSentTo($notifiable, $withSameFingerprint = false): NotificationHistoryQueryBuilder;

    public function fingerprint($notifiable): string;
}
