<?php

declare(strict_types=1);

namespace Budiardianata\PlainSqsDriver\Handler;

use Budiardianata\PlainSqsDriver\Contract\PlainSqsHandler;
use Illuminate\Contracts\Queue\Job;
use LogicException;

class DefaultPlainSqsHandler implements PlainSqsHandler
{
    public function handle(Job $job, ?array $data): void
    {
        throw new LogicException('No valid plain SQS handler is configured. Set plain-sqs.handlers.default or plain-sqs.handlers.{queue-name}.');
    }
}
