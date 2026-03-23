<?php

namespace Okaufmann\LaravelNotificationLog\Loggers;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\SentMessage;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Channels\VonageSmsChannel;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackChannel;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use JsonSerializable;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;
use NotificationChannels\Twilio\TwilioChannel;
use NotificationChannels\Twilio\TwilioSmsMessage;
use NotificationChannels\WebPush\WebPushChannel;
use Okaufmann\LaravelNotificationLog\Contracts\ResendableNotification;
use Okaufmann\LaravelNotificationLog\Contracts\ResolveMessageForLogging;
use Okaufmann\LaravelNotificationLog\Contracts\ResolveMessageForLoggingAfterSent;
use Okaufmann\LaravelNotificationLog\Contracts\ShouldLogNotification;
use Okaufmann\LaravelNotificationLog\Models\SentNotificationLog;
use Okaufmann\LaravelNotificationLog\NotificationDeliveryStatus;
use Spatie\Regex\Regex;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

class NotificationLogger
{
    public function logSkippedNotification(NotificationSending $event): ?SentNotificationLog
    {
        if (! $event->notification instanceof ShouldLogNotification) {
            return null;
        }

        $this->increaseNotificationAttempt($event);

        /** @var SentNotificationLog $sentNotificationLog */
        $sentNotificationLog = $this->getNotificationModelType()::query()->firstOrNew([
            'notification_id' => $this->getNotificationId($event->notification),
            'notification_type' => $this->getNotificationType($event),
            'channel' => $this->resolveChannel($event->channel),
            'attempt' => $event->notification->getCurrentAttempt(),
        ]);

        $data = [
            ...$sentNotificationLog->data ?? [],
            ...$this->buildSendingChannelData($event->channel, $event->notification, $event->notifiable),
            ...$this->buildExtraNotificationData($event->notification),
        ];

        $sentNotificationLog->fill([
            'notifiable_type' => $this->getNotifiableType($event),
            'notifiable_id' => $this->getNotifiableKey($event),
            'anonymous_notifiable_routes' => $this->getAnonymousRoutes($event),
            'fingerprint' => $this->getFingerprintForNotification($event->notification, $event->notifiable),
            'queued' => in_array(ShouldQueue::class, class_implements($event->notification)),
            'message' => $this->resolveMessage($event->channel, $event->notification, $event->notifiable),
            'status' => NotificationDeliveryStatus::SKIPPED,
            'data' => $data,
            'notification_serialized' => $this->serializeNotification($event->notification),
        ]);

        $sentNotificationLog->save();

        return $sentNotificationLog;
    }

    public function logSendingNotification(NotificationSending $event): ?SentNotificationLog
    {
        if (! $event->notification instanceof ShouldLogNotification) {
            return null;
        }

        $this->increaseNotificationAttempt($event);

        /** @var SentNotificationLog $sentNotificationLog */
        $sentNotificationLog = $this->getNotificationModelType()::query()->firstOrNew([
            'notification_id' => $this->getNotificationId($event->notification),
            'notification_type' => $this->getNotificationType($event),
            'channel' => $this->resolveChannel($event->channel),
            'attempt' => $event->notification->getCurrentAttempt(),
        ]);

        $data = [
            ...$sentNotificationLog->data ?? [],
            ...$this->buildSendingChannelData($event->channel, $event->notification, $event->notifiable),
            ...$this->buildExtraNotificationData($event->notification),
        ];

        if ($event->notification instanceof ResendableNotification && $event->notification->isBeingResent()) {
            $data['was_resent'] = true;
        }

        $sentNotificationLog->fill([
            'notifiable_type' => $this->getNotifiableType($event),
            'notifiable_id' => $this->getNotifiableKey($event),
            'anonymous_notifiable_routes' => $this->getAnonymousRoutes($event),
            'fingerprint' => $this->getFingerprintForNotification($event->notification, $event->notifiable),
            'queued' => in_array(ShouldQueue::class, class_implements($event->notification)),
            'message' => $this->resolveMessage($event->channel, $event->notification, $event->notifiable),
            'status' => NotificationDeliveryStatus::SENDING,
            'data' => $data,
            'notification_serialized' => $this->serializeNotification($event->notification),
        ]);

        $sentNotificationLog->save();

        return $sentNotificationLog;
    }

    public function logSentNotification(NotificationSent $event): ?SentNotificationLog
    {
        if (! $event->notification instanceof ShouldLogNotification) {
            return null;
        }

        /** @var SentNotificationLog $sentNotificationLog */
        $sentNotificationLog = $this->getNotificationModelType()::query()->firstOrNew([
            'notification_id' => $this->getNotificationId($event->notification),
            'notification_type' => $this->getNotificationType($event),
            'channel' => $this->resolveChannel($event->channel),
            'attempt' => $event->notification->getCurrentAttempt(),
        ]);

        $data = [
            ...$sentNotificationLog->data ?? [],
            ...$this->buildSentChannelData($event->channel, $event->notification, $event->notifiable, $event->response),
            'response' => $this->formatResponse($event->response),
        ];

        // Resolve message after sending if the notification implements the interface and config is enabled
        if (config('notification-log.resolve_notification_message')) {
            $resolvedMessage = $this->resolveMessageAfterSent($event->channel, $event->notification, $event->notifiable, $event->response);
            if ($resolvedMessage !== null) {
                $sentNotificationLog->message = $resolvedMessage;
            }
        }

        $sentNotificationLog->status = NotificationDeliveryStatus::SENT;
        $sentNotificationLog->sent_at = now()->toImmutable();
        $sentNotificationLog->data = $data;

        $sentNotificationLog->save();

        return $sentNotificationLog;
    }

    public function logFailedNotification(NotificationFailed $event): ?SentNotificationLog
    {
        if (! $event->notification instanceof ShouldLogNotification) {
            return null;
        }

        // there may be the case a channel already fires a NotificationFailed event.
        // this is the case for several channels because the implementations are very inconsistent.
        $findData = [
            'notification_id' => $this->getNotificationId($event->notification),
            'notification_type' => $this->getNotificationType($event),
            'channel' => $this->resolveChannel($event->channel),
            'attempt' => $event->notification->getCurrentAttempt(),
        ];

        $notificationLog = $this->getNotificationModelType()::query()
            ->where($findData)
            ->first();

        if (! $notificationLog) {
            // a notification needs to at least be in status sending first.
            // therefore it must exist in the logs table before can be declared as failed.
            return null;
        }

        if ($notificationLog->status === NotificationDeliveryStatus::FAILED) {
            // if the notification is already marked as failed, we won't update it again.
            // most of the time this will be caused by the fact the channel handles failed notifications itself
            // and our custom notification sender caught the exception and fired another FailedNotification event.
            return $notificationLog;
        }

        // since Laravel v12.11.0 https://github.com/laravel/framework/releases/tag/v12.11.0 sendToNotifiable is finally raising a NotificationFailed
        // we need to write the message to keep backward compatibility
        if (isset($event->data['exception']) && $event->data['exception'] instanceof \Exception) {
            $event->data['message'] = $event->data['exception']->getMessage();
        }

        $notificationLog = $this->getNotificationModelType()::updateOrCreate(
            $findData,
            [
                'data' => $this->normalizeFailedNotificationData($event->data),
                'status' => NotificationDeliveryStatus::FAILED,
            ]);

        return $notificationLog;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeFailedNotificationData(array $data): array
    {
        if (isset($data['exception']) && $data['exception'] instanceof \Throwable) {
            $exception = $data['exception'];
            $data['message'] = $data['message'] ?? $exception->getMessage();
            $data['exception'] = [
                'class' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }

        return $data;
    }

    public function resolveMessage(string $channel, Notification $notification, $notifiable)
    {
        if (! config('notification-log.resolve_notification_message')) {
            return null;
        }

        $channelManager = resolve(ChannelManager::class);
        $channel = $channelManager->driver($channel);

        try {
            if ($notification instanceof ResolveMessageForLogging) {
                $message = $notification->resolveMessageForLogging($channel, $notifiable);

                return $message;
            }

            if ($channel instanceof MailChannel) {
                $message = $notification->toMail($notifiable);

                if ($message instanceof Renderable) {
                    return $message->render();
                }

                return null;
            }

            if ($channel instanceof VonageSmsChannel) {
                $message = $notification->toVonage($notifiable);

                if (is_string($message)) {
                    $message = new VonageMessage($message);
                }

                return $message->content;
            }

            if ($channel instanceof TwilioChannel) {
                $message = $notification->toTwilio($notifiable);

                if (is_string($message)) {
                    $message = new TwilioSmsMessage($message);
                }

                return $message->content;
            }

            if ($channel instanceof TelegramChannel) {
                $message = $notification->toTelegram($notifiable);
                if (is_string($message)) {
                    $message = TelegramMessage::create($message);
                }

                return json_encode($message->toArray());
            }

            if ($channel instanceof WebPushChannel) {
                $message = $notification->toWebPush($notifiable, $notification);

                return json_encode($message->toArray());
            }

            if ($channel instanceof SlackChannel) {
                $message = $notification->toSlack($notifiable, $notification);

                return json_encode($message->toArray());
            }

            if ($channel instanceof DatabaseChannel) {
                if (method_exists($notification, 'toDatabase')) {
                    return is_array($data = $notification->toDatabase($notifiable))
                        ? $data : null;
                }

                if (method_exists($notification, 'toArray')) {
                    return json_encode($notification->toArray($notifiable));
                }
            }

            return null;
        } catch (\Throwable $e) {
            if (app()->runningUnitTests()) {
                throw $e;
            }

            return null;
        }
    }

    public function resolveMessageAfterSent(string $channel, Notification $notification, $notifiable, $response): ?string
    {
        if (! config('notification-log.resolve_notification_message')) {
            return null;
        }

        $channelManager = resolve(ChannelManager::class);
        $channel = $channelManager->driver($channel);

        try {
            if ($notification instanceof ResolveMessageForLoggingAfterSent) {
                $message = $notification->resolveMessageForLoggingAfterSent($channel, $notifiable, $response);

                return $message;
            }

            return null;
        } catch (\Throwable $e) {
            if (app()->runningUnitTests()) {
                throw $e;
            }

            return null;
        }
    }

    public function getFingerprintForNotification(Notification $notification, $notifiable)
    {
        if (method_exists($notification, 'fingerprint')) {
            return $notification->fingerprint($notifiable);
        }

        return null;
    }

    public function getNotificationTypeForNotification(Notification $notification, $notifiable)
    {
        if (method_exists($notification, 'logType')) {
            return $notification->logType($notifiable);
        }

        return get_class($notification);
    }

    public function resolveChannel($channel)
    {
        if (blank($channel) || ! class_exists($channel)) {
            return $channel;
        }

        return $this->shortNameFromChannelType($channel);
    }

    protected function shortNameFromChannelType($type): string
    {
        // Extract the last part of the namespace and class name
        $parts = explode('\\', $type);
        $className = end($parts);

        return Str::of($className)
            ->replace('Channel', '')
            ->lower();
    }

    protected function getNotifiableType(NotificationSending $event): ?string
    {
        /** @var Model|AnonymousNotifiable $notifiable */
        $notifiable = $event->notifiable;

        return $notifiable instanceof Model
            ? $notifiable->getMorphClass()
            : null;
    }

    protected function getNotifiableKey(NotificationSending $event): mixed
    {
        /** @var Model|AnonymousNotifiable $notifiable */
        $notifiable = $event->notifiable;

        return $notifiable instanceof Model
            ? $notifiable->getKey()
            : null;
    }

    protected function getNotificationType(NotificationSending|NotificationSent|NotificationFailed $event): string
    {
        $notification = $event->notification;

        return $this->getNotificationTypeForNotification($notification, $event->notifiable);
    }

    protected function getAnonymousRoutes(NotificationSending $event): ?array
    {
        if (! $event->notifiable instanceof AnonymousNotifiable) {
            return null;
        }

        return $event->notifiable->routes;
    }

    /**
     * Format the given notifiable into a tag.
     *
     * @param  mixed  $notifiable
     */
    protected function formatNotifiable($notifiable): string
    {
        if ($notifiable instanceof Model) {
            return get_class($notifiable).':'.implode('_', Arr::wrap($notifiable->getKey()));
        }

        if ($notifiable instanceof AnonymousNotifiable) {
            $routes = array_map(function ($route) {
                return is_array($route) ? implode(',', $route) : $route;
            }, $notifiable->routes);

            return 'Anonymous:'.implode(',', $routes);
        }

        return get_class($notifiable);
    }

    protected function formatResponse($response): ?array
    {
        if (is_string($response)) {
            return [
                'message' => $response,
            ];
        }

        if (is_object($response) && method_exists($response, 'toArray')) {
            return $response->toArray();
        }

        if (is_object($response) && method_exists($response, 'toJson')) {
            return $response->toJson();
        }

        if ($response instanceof JsonSerializable) {
            return json_decode(json_encode($response), true, 512, JSON_THROW_ON_ERROR);
        }

        if (is_array($response)) {
            return $response;
        }

        return null;
    }

    protected function getNotificationId(Notification $notification): string
    {
        if (! $notification->id) {
            $notification->id = (string) Str::uuid();
        }

        return $notification->id;
    }

    private function increaseNotificationAttempt(NotificationSending $event): void
    {
        assert($event->notification instanceof ShouldLogNotification);

        $currentAttempt = $this->getNotificationModelType()::query()
            ->where('notification_id', $this->getNotificationId($event->notification))
            ->where('channel', $event->channel)
            ->max('attempt');

        // when you retry a job after it failed after several tries, the attempt of the instance will be reset as
        // it will be pushed as a new instance with the same notification id to the queue.
        // Therefor we need to increment the attempt manually by looking it up in the logs table.
        if ($currentAttempt > $event->notification->getCurrentAttempt()) {
            $event->notification->setCurrentAttempt($currentAttempt + 1);
        } else {
            $event->notification->setCurrentAttempt();
        }
    }

    private function buildSentChannelData(string $channel, Notification $notification, $notifiable, mixed $response)
    {
        if ($response instanceof SentMessage) {
            $rawMessage = $response->getSymfonySentMessage()->getOriginalMessage();

            if (! $rawMessage instanceof Email) {
                return [];
            }

            return [
                'message_id' => $this->cleanMessageId($response->getMessageId()),
                'from' => $this->listEmailAddresses($rawMessage->getFrom()),
                'to' => $this->listEmailAddresses($rawMessage->getTo()),
                'cc' => $this->listEmailAddresses($rawMessage->getCc()),
                'bcc' => $this->listEmailAddresses($rawMessage->getBcc()),
                'reply_to' => $this->listEmailAddresses($rawMessage->getReplyTo()),
                'sender' => $this->listEmailAddresses([$rawMessage->getSender()]),
                'subject' => $rawMessage->getSubject(),
                'sent_at' => $rawMessage->getDate(),
                'attachments' => $this->listEmailAttachments($rawMessage->getAttachments()),
            ];
        }

        return [];
    }

    /**
     * @param  Address[]  $addresses
     * @return ?string[]
     */
    protected function listEmailAddresses(?array $addresses): ?array
    {
        $addresses = collect($addresses)
            ->filter()
            ->map(fn (Address $address) => $address->getName() ? "{$address->getName()} <{$address->getAddress()}>" : $address->getAddress())
            ->values()
            ->toArray();

        if (blank($addresses)) {
            return null;
        }

        return $addresses;
    }

    /**
     * @param  DataPart[]  $getAttachments
     */
    protected function listEmailAttachments(array $getAttachments): array
    {
        return collect($getAttachments)
            ->map(fn (DataPart $attachment) => $attachment->getFilename())
            ->toArray();
    }

    private function buildExtraNotificationData(Notification $notification)
    {
        if (method_exists($notification, 'getExtraData')) {
            $extra = $notification->getExtraData();

            if (! is_array($extra)) {
                throw new \InvalidArgumentException('getExtraData() must return an array');
            }

            return $extra;
        }

        return [];
    }

    protected function buildSendingChannelData(string $channel, Notification $notification, mixed $notifiable)
    {
        $channelManager = resolve(ChannelManager::class);
        $channel = $channelManager->driver($channel);

        if ($channel instanceof MailChannel) {

            /** @var MailMessage $message */
            $message = $notification->toMail($notifiable);

            $from = null;
            if ($message->from) {
                $from = new Address($message->from[0], $message->from[1] ?? null);
            }

            $toRoute = $notifiable->routeNotificationFor('mail', $notification);
            $to = null;
            if ($toRoute) {
                $name = is_string($toRoute) ? $toRoute : $toRoute[0];
                $address = is_string($toRoute) ? '' : $toRoute[1] ?? '';
                $to = new Address($name, $address);
            }

            $ccAddresses = null;
            if ($message->cc) {
                $ccAddresses = $this->extractMailAddresses($message->cc);
            }

            $bccAddresses = null;
            if ($message->bcc) {
                $bccAddresses = $this->extractMailAddresses($message->bcc);
            }

            $replyToAddresses = null;
            if ($message->replyTo) {
                $replyToAddresses = $this->extractMailAddresses($message->replyTo);
            }

            $attachments = $this->extractAttachmentNamesFromMessage($message);

            return [
                'from' => $this->listEmailAddresses([$from]),
                'to' => $this->listEmailAddresses([$to]),
                'cc' => $this->listEmailAddresses($ccAddresses),
                'bcc' => $this->listEmailAddresses($bccAddresses),
                'reply_to' => $this->listEmailAddresses($replyToAddresses),
                'subject' => $message->subject,
                'attachments' => $attachments,
            ];
        }

        return [];
    }

    protected function arrayOfAddresses($address)
    {
        return is_iterable($address) || $address instanceof Arrayable;
    }

    protected function extractMailAddresses(array $addresses): array
    {
        if ($this->arrayOfAddresses($addresses)) {
            return collect($addresses)
                ->map(function ($address, $name) {
                    $addressName = is_array($address) ? $address[1] ?? '' : '';
                    if (! is_null($addressName)) {
                        $addressName = '';
                    }

                    $addressEmail = is_array($address) ? $address[0] : $address;

                    return new Address($addressEmail, $addressName);
                })
                ->toArray();
        }

        return [new Address($addresses[0], $addresses[1] ?? '')];
    }

    protected function extractAttachmentNamesFromMessage(MailMessage $message): array
    {
        return collect([
            ...$message->rawAttachments, ...$message->attachments,
        ])
            ->map(function ($attachment) {
                if (isset($attachment['name'])) {
                    return $attachment['name'];
                }

                if (isset($attachment['file']) && is_string($attachment['file'])) {
                    return basename($attachment['file']);
                }

                return null;
            })
            ->filter()
            ->toArray();
    }

    protected function serializeNotification(Notification $notification)
    {
        if (! config('notification-log.store_serialized_notifications')) {
            return null;
        }

        return serialize($notification);
    }

    protected function cleanMessageId(?string $messageId): string
    {
        if (blank($messageId)) {
            return '';
        }

        $matchResult = Regex::match('/<(.*)>/', $messageId);
        if (! $matchResult->hasMatch()) {
            return $messageId;
        }

        return $matchResult->group(1);
    }

    protected function getNotificationModelType(): string
    {
        return config('notification-log.model');
    }
}
