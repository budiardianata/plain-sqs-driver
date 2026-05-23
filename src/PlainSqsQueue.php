<?php

declare(strict_types=1);

namespace Budiardianata\PlainSqsDriver;

use Budiardianata\PlainSqsDriver\Contract\PlainSqsHandler;
use Budiardianata\PlainSqsDriver\Job\PlainSqsDispatcherJob;
use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Jobs\SqsJob;
use Illuminate\Queue\SqsQueue;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;

class PlainSqsQueue extends SqsQueue
{
    /**
     * Buffered SQS messages from a single receiveMessage call.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $messagesBuffer = [];

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

        if ($this->messagesBuffer === []) {
            $response = $this->sqs->receiveMessage([
                'QueueUrl' => $queue,
                'AttributeNames' => ['ApproximateReceiveCount'],
                'MaxNumberOfMessages' => $this->getMaxNumberOfMessages(),
                'WaitTimeSeconds' => $this->getWaitTimeSeconds(),
                'MessageAttributeNames' => ['All'],
            ]);

            $this->messagesBuffer = $response['Messages'] ?? [];
        }

        if ($this->messagesBuffer !== []) {
            $queueId = explode('/', $queue);
            $queueId = array_pop($queueId);
            $class = $this->getClassName($queueId);

            $message = array_shift($this->messagesBuffer);

            if (! is_array($message)) {
                return null;
            }

            $response = $this->modifyPayload($message, $class);

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

    private function getMaxNumberOfMessages(): int
    {
        $configured = Config::get('plain-sqs.max_number_of_messages', 1);

        if (! is_numeric($configured)) {
            return 1;
        }

        return max(1, min(10, (int) $configured));
    }

    private function getWaitTimeSeconds(): int
    {
        $configured = Config::get('plain-sqs.wait_time_seconds', 2);
        if (! is_numeric($configured)) {
            return 1;
        }

        return max(0, min(20, (int) $configured));
    }

    private function getClass($queue = null): string
    {
        if (! $queue) {
            return $this->getClassName('default');
        }

        $queueId = explode('/', $queue);
        $queueId = array_pop($queueId);

        return $this->getClassName($queueId);
    }

    private function getClassName(string $queueId): string
    {
        $handlers = Config::get('plain-sqs.handlers', []);
        $class = (array_key_exists($queueId, $handlers))
            ? $handlers[$queueId]
            : ($handlers['default'] ?? null);

        if (! is_string($class) || ! class_exists($class)) {
            throw new InvalidArgumentException('Invalid plain SQS handler class configured for queue: '.$queueId);
        }

        if (! is_a($class, PlainSqsHandler::class, true)) {
            throw new InvalidArgumentException(sprintf(
                'Configured plain SQS handler [%s] must implement [%s].',
                $class,
                PlainSqsHandler::class
            ));
        }

        return $class;
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
