<?php

declare(strict_types=1);

namespace Budiardianata\PlainSqsDriver;

use Budiardianata\PlainSqsDriver\Job\PlainSqsDispatcherJob;
use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Jobs\SqsJob;
use Illuminate\Queue\SqsQueue;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use JsonException;

class PlainSqsQueue extends SqsQueue
{
    /**
     * Pop the next job off of the queue.
     *
     * @param  string  $queue
     *
     * @throws JsonException
     */
    public function pop($queue = null): ?Job
    {
        $queue = $this->getQueue($queue);

        $response = $this->sqs->receiveMessage([
            'QueueUrl' => $queue,
            'AttributeNames' => ['ApproximateReceiveCount'],
            'MaxNumberOfMessages' => 1,
            'MessageAttributeNames' => ['All'],
        ]);

        if (isset($response['Messages']) && count($response['Messages']) > 0) {
            $queueId = explode('/', $queue);
            $queueId = array_pop($queueId);
            $class = $this->getClassName($queueId);

            $response = $this->modifyPayload($response['Messages'][0], $class);

            return new SqsJob($this->container, $this->sqs, $response, $this->connectionName, $queue);
        }

        return null;
    }

    /**
     * @param  string  $payload
     */
    public function pushRaw($payload, $queue = null, array $options = []): mixed
    {
        $payload = json_decode($payload, true);

        if (isset($payload['data']) && isset($payload['job'])) {
            $payload = $payload['data'];
        }

        return parent::pushRaw(json_encode($payload), $queue, $options);
    }

    /**
     * Create a payload string from the given job and data.
     *
     * @param  Closure|object|string  $job
     * @param  mixed  $data
     * @param  string  $queue
     * @param  DateInterval|DateTimeInterface|int|null  $delay
     *
     * @throws JsonException
     */
    protected function createPayload($job, $queue, $data = '', $delay = null): string
    {
        if (! $job instanceof PlainSqsDispatcherJob) {
            return parent::createPayload($job, $queue, $data, $delay);
        }

        $handlerJob = $this->getClass($queue).'@handle';

        return $job->isPlain() ? \json_encode($job->getPayload(), JSON_THROW_ON_ERROR) : \json_encode([
            'job' => $handlerJob,
            'data' => $job->getPayload(),
        ], JSON_THROW_ON_ERROR);
    }

    private function getClass($queue = null): string
    {
        if (! $queue) {
            return Config::get('plain-sqs.default-handler');
        }

        $queueId = explode('/', $queue);
        $queueId = array_pop($queueId);

        return $this->getClassName($queueId);
    }

    private function getClassName(string $queueId): string
    {
        return (array_key_exists($queueId, Config::get('plain-sqs.handlers')))
            ? Config::get('plain-sqs.handlers')[$queueId]
            : Config::get('plain-sqs.default-handler');
    }

    /**
     * @throws JsonException
     */
    private function modifyPayload(array|string $payload, string $class): array|string
    {
        if (! is_array($payload)) {
            $payload = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        }

        $body = json_decode($payload['Body'], true, 512, JSON_THROW_ON_ERROR);

        $payload['Body'] = json_encode([
            'uuid' => $payload['MessageId'] ?? (string) Str::uuid(),
            'job' => $class.'@handle',
            'data' => $body['data'] ?? $body,
        ], JSON_THROW_ON_ERROR);

        return $payload;
    }
}
