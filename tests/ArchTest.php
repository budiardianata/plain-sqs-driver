<?php

declare(strict_types=1);

use Aws\Sqs\SqsClient;
use Budiardianata\PlainSqsDriver\Contract\PlainSqsHandler;
use Budiardianata\PlainSqsDriver\Job\PlainSqsDispatcherJob;
use Budiardianata\PlainSqsDriver\PlainSqsConnector;
use Budiardianata\PlainSqsDriver\PlainSqsQueue;
use Illuminate\Contracts\Queue\Job as QueueJobContract;
use Illuminate\Queue\Jobs\SqsJob;

it('creates plain sqs queue connector with credentials', function () {
    $queueManager = app('queue');
    $getConnector = new ReflectionMethod($queueManager, 'getConnector');
    $connector = $getConnector->invoke($queueManager, 'sqs-plain');

    expect($connector)->toBeInstanceOf(PlainSqsConnector::class);
});

it('builds wrapped payload in dispatcher default mode', function () {
    config()->set('plain-sqs.handlers.default', DummyPlainSqsHandler::class);

    $job = new PlainSqsDispatcherJob(['event' => 's3.created']);

    expect($job->isPlain())->toBeFalse()
        ->and($job->getPayload())->toBe([
            'job' => DummyPlainSqsHandler::class,
            'data' => ['event' => 's3.created'],
        ]);
});

it('builds plain payload in dispatcher plain mode', function () {
    $job = new PlainSqsDispatcherJob(['event' => 's3.created'])->setPlain();

    expect($job->isPlain())->toBeTrue()
        ->and($job->getPayload())->toBe(['event' => 's3.created']);
});

it('creates wrapped send payload for non plain dispatcher job', function () {
    config()->set('plain-sqs.handlers', [
        'default' => DummyPlainSqsHandler::class,
        'plain-sqs' => DummyPlainSqsHandler::class,
    ]);

    $queue = new TestablePlainSqsQueue(
        mock(SqsClient::class),
        'plain-sqs',
        'https://sqs.ap-southeast-1.amazonaws.com/123456789012'
    );

    $payload = json_decode($queue->publicCreatePayload(new PlainSqsDispatcherJob(['event' => 's3.created']), 'plain-sqs'), true);

    expect($payload)
        ->toHaveKey('job', DummyPlainSqsHandler::class.'@handle')
        ->and($payload['data'])->toBe([
            'job' => DummyPlainSqsHandler::class,
            'data' => ['event' => 's3.created'],
        ]);
});

it('receives plain sqs payload and transforms it into laravel job payload', function () {
    config()->set('plain-sqs.handlers', [
        'default' => DummyPlainSqsHandler::class,
        'plain-sqs' => DummyPlainSqsHandler::class,
    ]);

    $sqs = mock(SqsClient::class);
    $sqs->shouldReceive('receiveMessage')->once()->andReturn([
        'Messages' => [[
            'MessageId' => 'message-123',
            'ReceiptHandle' => 'receipt-handle-123',
            'Body' => json_encode(['source' => 's3', 'event' => 'ObjectCreated']),
            'Attributes' => ['ApproximateReceiveCount' => '1'],
            'MessageAttributes' => [],
        ]],
    ]);

    $queue = new PlainSqsQueue(
        $sqs,
        'plain-sqs',
        'https://sqs.ap-southeast-1.amazonaws.com/123456789012'
    );
    $queue->setContainer(app());
    $queue->setConnectionName('sqs_plain');

    $job = $queue->pop();

    expect($job)
        ->toBeInstanceOf(SqsJob::class)
        ->and($job->payload())->toBe([
            'uuid' => 'message-123',
            'job' => DummyPlainSqsHandler::class.'@handle',
            'data' => ['source' => 's3', 'event' => 'ObjectCreated'],
        ]);
});

it('loads multiple messages in one receive call and keeps attempts for retries logic', function () {
    config()->set('plain-sqs.handlers', [
        'default' => DummyPlainSqsHandler::class,
        'plain-sqs' => DummyPlainSqsHandler::class,
    ]);
    config()->set('plain-sqs.max_number_of_messages', 2);

    $sqs = mock(SqsClient::class);
    $sqs->shouldReceive('receiveMessage')->once()->andReturn([
        'Messages' => [
            [
                'MessageId' => 'message-1',
                'ReceiptHandle' => 'receipt-handle-1',
                'Body' => json_encode(['source' => 's3', 'event' => 'ObjectCreated']),
                'Attributes' => ['ApproximateReceiveCount' => '3'],
                'MessageAttributes' => [],
            ],
            [
                'MessageId' => 'message-2',
                'ReceiptHandle' => 'receipt-handle-2',
                'Body' => json_encode(['source' => 's3', 'event' => 'ObjectRemoved']),
                'Attributes' => ['ApproximateReceiveCount' => '1'],
                'MessageAttributes' => [],
            ],
        ],
    ]);

    $queue = new PlainSqsQueue(
        $sqs,
        'plain-sqs',
        'https://sqs.ap-southeast-1.amazonaws.com/123456789012'
    );
    $queue->setContainer(app());
    $queue->setConnectionName('sqs_plain');

    $firstJob = $queue->pop();
    $secondJob = $queue->pop();

    expect($firstJob)
        ->toBeInstanceOf(SqsJob::class)
        ->and($firstJob->attempts())->toBe(3)
        ->and($firstJob->payload()['job'])->toBe(DummyPlainSqsHandler::class.'@handle')
        ->and($secondJob)
        ->toBeInstanceOf(SqsJob::class)
        ->and($secondJob->attempts())->toBe(1)
        ->and($secondJob->payload()['job'])->toBe(DummyPlainSqsHandler::class.'@handle');
});

it('throws exception when configured handler does not implement interface', function () {
    config()->set('plain-sqs.handlers', [
        'default' => InvalidPlainSqsHandler::class,
        'plain-sqs' => InvalidPlainSqsHandler::class,
    ]);

    $queue = new TestablePlainSqsQueue(
        mock(SqsClient::class),
        'plain-sqs',
        'https://sqs.ap-southeast-1.amazonaws.com/123456789012'
    );

    expect(fn () => $queue->publicCreatePayload(new PlainSqsDispatcherJob(['event' => 's3.created']), 'plain-sqs'))
        ->toThrow(InvalidArgumentException::class);
});

final class DummyPlainSqsHandler implements PlainSqsHandler
{
    public function handle(QueueJobContract $job, ?array $data): void {}
}

final class InvalidPlainSqsHandler
{
    public function handle(): void {}
}

final class TestablePlainSqsQueue extends PlainSqsQueue
{
    public function publicCreatePayload(mixed $job, string $queue): string
    {
        return $this->createPayload($job, $queue);
    }
}
