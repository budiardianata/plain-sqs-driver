<?php

declare(strict_types=1);

namespace Budiardianata\PlainSqsDriver\Contract;

use Illuminate\Contracts\Queue\Job;

interface PlainSqsHandler
{
    public function handle(Job $job, ?array $data): void;
}
