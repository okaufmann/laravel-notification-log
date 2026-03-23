<?php

namespace Okaufmann\LaravelNotificationLog\Manager;

use Illuminate\Contracts\Bus\Dispatcher as Bus;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Notifications\ChannelManager as BaseChannelManager;
use Illuminate\Support\Collection;
use Override;

class ChannelManager extends BaseChannelManager
{
    /**
     * Send the given notification to the given notifiable entities.
     *
     * @param  Collection|array|mixed  $notifiables
     * @param  mixed  $notification
     * @return void
     */
    #[Override]
    public function send($notifiables, $notification)
    {
        (new NotificationSender(
            $this, $this->container->make(Bus::class), $this->container->make(Dispatcher::class), $this->locale)
        )->send($notifiables, $notification);
    }

    /**
     * Send the given notification immediately.
     *
     * @param  Collection|array|mixed  $notifiables
     * @param  mixed  $notification
     * @return void
     */
    #[Override]
    public function sendNow($notifiables, $notification, ?array $channels = null)
    {
        (new NotificationSender(
            $this, $this->container->make(Bus::class), $this->container->make(Dispatcher::class), $this->locale)
        )->sendNow($notifiables, $notification, $channels);
    }
}
