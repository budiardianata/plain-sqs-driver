# Plain SQS Driver for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/budiardianata/plain-sqs-driver.svg?style=flat-square)](https://packagist.org/packages/budiardianata/plain-sqs-driver)
[![Total Downloads](https://img.shields.io/packagist/dt/budiardianata/plain-sqs-driver.svg?style=flat-square)](https://packagist.org/packages/budiardianata/plain-sqs-driver)

`budiardianata/plain-sqs-driver` is a Laravel queue driver wrapper for consuming and dispatching **plain JSON SQS messages**.

This is useful when messages are produced outside Laravel (for example AWS S3 Event Notifications, EventBridge, or another service) but still need to be processed by Laravel queue workers.

## What this package does

- Registers a custom queue driver: `sqs-plain`
- Transforms raw/plain SQS message bodies into Laravel queue payload format
- Routes messages to your handler class based on queue name
- Supports dispatching either Laravel-style payloads or plain payloads

## Installation

```bash
composer require budiardianata/plain-sqs-driver
```

Publish package config:

```bash
php artisan vendor:publish --tag="plain-sqs-driver-config"
```

## Configuration

Add a queue connection in `config/queue.php`:

```php
'connections' => [
    'sqs_plain' => [
        'driver' => 'sqs-plain',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'prefix' => env('SQS_PREFIX', 'https://sqs.ap-southeast-1.amazonaws.com/your-account-id'),
        'queue' => env('SQS_QUEUE', 'your-queue-name'),
        'region' => env('AWS_DEFAULT_REGION', 'ap-southeast-1'),
    ],
],
```

Configure handlers in `config/plain-sqs.php`:

```php
return [
    'handlers' => [
        'your-queue-name' => App\Jobs\PlainSqsHandler::class,
    ],

    'default-handler' => App\Jobs\PlainSqsHandler::class,
];
```

## Usage

Run your worker against the `sqs_plain` connection:

```bash
php artisan queue:work sqs_plain
```

For every incoming SQS message, this package wraps the message so Laravel can execute your configured handler (`handle()` method).

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md) for more information.
