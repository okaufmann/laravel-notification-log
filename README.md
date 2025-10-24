# Laravel Notification Log

[![Latest Version on Packagist](https://img.shields.io/packagist/v/okaufmann/laravel-notification-log.svg?style=flat-square)](https://packagist.org/packages/okaufmann/laravel-notification-log)
[![Tests](https://github.com/okaufmann/laravel-notification-log/actions/workflows/run-tests.yml/badge.svg)](https://github.com/okaufmann/laravel-notification-log/actions/workflows/run-tests.yml)
[![PHPStan](https://github.com/okaufmann/laravel-notification-log/actions/workflows/phpstan.yml/badge.svg)](https://github.com/okaufmann/laravel-notification-log/actions/workflows/phpstan.yml)
[![Check & fix styling](https://github.com/okaufmann/laravel-notification-log/actions/workflows/php-code-style.yml/badge.svg)](https://github.com/okaufmann/laravel-notification-log/actions/workflows/php-code-style.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/okaufmann/laravel-notification-log.svg?style=flat-square)](https://packagist.org/packages/okaufmann/laravel-notification-log)

Logs every sent Notification of your entire Laravel Project.

## Installation

You can install the package via composer:

```bash
composer require okaufmann/laravel-notification-log
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="notification-log-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="notification-log-config"
```

The following config file will be published in config/notification-log.php:

```php
return [

    /*
    |--------------------------------------------------------------------------
    | Resolve Notification Message
    |--------------------------------------------------------------------------
    |
    | If this is enabled, the Logger will try to resolve the built message
    | out of the notification. This is useful if you want to debug your
    | sent notifications.
    |
    */

    'resolve_notification_message' => env('NOTIFICATION_LOG_RESOLVE_NOTIFICATION_MESSAGE', false),
];
```

## Usage

Add the following Interface and Trait to your Notification:

```php
use Okaufmann\LaravelNotificationLog\Contracts\ShouldLogNotification;use Okaufmann\LaravelNotificationLog\Models\Concerns\LogsNotifications;

class DummyNotification extends Notification implements ShouldLogNotification
{
    use LogsNotifications;

    // ...

}
```

Now send a Notification or Mail as you would normally do. The package will automatically log the Notification or Mail.

## Custom Message Resolution

If you want to customize how the notification message is resolved for logging purposes, you can implement the `ResolveMessageForLogging` interface:

```php
use Okaufmann\LaravelNotificationLog\Contracts\ShouldLogNotification;
use Okaufmann\LaravelNotificationLog\Contracts\ResolveMessageForLogging;
use Okaufmann\LaravelNotificationLog\Models\Concerns\LogsNotifications;

class CustomNotification extends Notification implements ShouldLogNotification, ResolveMessageForLogging
{
    use LogsNotifications;

    public function resolveMessageForLogging(string $channel, $notifiable): string
    {
        $channelName = get_class($channel);
        $notifiableName = get_class($notifiable);

        return "Custom message for {$channelName} channel sent to {$notifiableName}";
    }
}
```

When a notification implements `ResolveMessageForLogging`, the logger will use your custom method instead of the default message resolution logic. This gives you full control over what gets stored in the `message` field of the notification log.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

[//]: # (## Contributing)

[//]: # ()
[//]: # (Please see [CONTRIBUTING]&#40;CONTRIBUTING.md&#41; for details.)

## Credits

- [Oliver Kaufmann](https://github.com/okaufmann)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
