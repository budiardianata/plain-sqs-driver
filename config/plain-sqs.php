<?php

declare(strict_types=1);

use Budiardianata\PlainSqsDriver\Handler\DefaultPlainSqsHandler;

/**
 * Map SQS queue names to handler classes that implement
 * Budiardianata\PlainSqsDriver\Contract\PlainSqsHandler.
 */
return [
    /**
     * Specifies the number of seconds that the SQS connection stays open waiting for a message to arrive if the queue is currently empty.
     * Allowed Values: 0 to 20 seconds.
     */
    'wait_time_seconds' => 20,

    /**
     * Specifies the absolute maximum number of messages you want SQS to return in a single ReceiveMessage call.
     * Allowed Values: 1 to 10 messages (the default is 1).
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
