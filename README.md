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
- Implement in-memory message buffer to prefetch up to 10 messages per SQS API call.

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
    /**
     * Specifies the number of seconds that the SQS connection stays open waiting for a message to arrive if the queue is currently empty.
     */
    'wait_time_seconds' => 20,

    /**
     * Specifies the absolute maximum number of messages you want SQS to return in a single ReceiveMessage call.
     */
    'max_number_of_messages' => 1,

    'handlers' => [
        /**
         * Do not delete the 'default' handler key, only change the class with your own implementation
         */
        'default' => DefaultPlainSqsHandler::class,

        // 'plain-sqs' => App\Jobs\S3NotificationHandler::class,
    ],
];
```

- `wait_time_seconds`: SQS long-poll time (1-20).
- `max_number_of_messages`: batch prefetch size per receive call (1-10). Use `> 1` to let one worker load multiple messages in memory and process them one-by-one.

Create your handler class and implement package interface `PlainSqsHandler`:

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use Budiardianata\PlainSqsDriver\Contract\PlainSqsHandler;
use Illuminate\Contracts\Queue\Job;

class S3NotificationHandler implements PlainSqsHandler
{
    public function handle(Job $job, ?array $data): void
    {
        // handle plain SQS payload from external producer
    }
}
```

Every class configured in `plain-sqs.handlers` (including `plain-sqs.handlers.default`) must implement `PlainSqsHandler`.

## Usage

Run your worker against the `sqs_plain` connection:

```bash
php artisan queue:work sqs_plain
```

For retry safety with queue worker retries (`--tries`), keep default Laravel failure flow and do not force-delete failed jobs. This driver preserves SQS `ApproximateReceiveCount`, so retries are still evaluated correctly by Laravel worker.

For every incoming SQS message, this package wraps the message so Laravel can execute your configured handler (`handle()` method).

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md) for more information.
