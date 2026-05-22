<?php

declare(strict_types=1);

namespace Budiardianata\PlainSqsDriver;

use Aws\Sqs\SqsClient;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\Connectors\SqsConnector;
use Illuminate\Support\Arr;

class PlainSqsConnector extends SqsConnector
{
    public function connect(array $config): Queue
    {
        $config = $this->getDefaultConfiguration($config);

        if (isset($config['key'], $config['secret'])) {
            $config['credentials'] = Arr::only($config, ['key', 'secret']);
        }

        return new PlainSqsQueue(
            new SqsClient($config),
            $config['queue'],
            Arr::get($config, 'prefix', '')
        );
    }
}
