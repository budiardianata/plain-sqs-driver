<?php

declare(strict_types=1);
use App\Jobs\PlainSqsHandler;

/**
 * List of plain SQS queues and their corresponding handling classes
 */
return [
    // Separate queue handler with corresponding queue name as key.
    'handlers' => [
        'plain-sqs' => PlainSqsHandler::class,
    ],

    // If no handlers specified then default handler will be executed.
    'default-handler' => PlainSqsHandler::class,
];
