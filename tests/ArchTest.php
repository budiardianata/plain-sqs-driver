<?php

declare(strict_types=1);

use Aws\Sqs\SqsClient;
use Budiardianata\PlainSqsDriver\Job\PlainSqsDispatcherJob;
use Budiardianata\PlainSqsDriver\PlainSqsConnector;
use Budiardianata\PlainSqsDriver\PlainSqsQueue;
use Illuminate\Queue\Jobs\SqsJob;

it('creates plain sqs queue connector with credentials', function () {
    $queueManager = app('queue');
    $getConnector = new ReflectionMethod($queueManager, 'getConnector');
    $connector = $getConnector->invoke($queueManager, 'sqs-plain');

    expect($connector)->toBeInstanceOf(PlainSqsConnector::class);
});

it('builds wrapped payload in dispatcher default mode', function () {
    config()->set('plain-sqs.default-handler', DummyPlainSqsHandler::class);

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
    config()->set('plain-sqs.default-handler', DummyPlainSqsHandler::class);
    config()->set('plain-sqs.handlers', ['plain-sqs' => DummyPlainSqsHandler::class]);

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
    config()->set('plain-sqs.handlers', ['plain-sqs' => DummyPlainSqsHandler::class]);
    config()->set('plain-sqs.default-handler', DummyPlainSqsHandler::class);

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

final class DummyPlainSqsHandler
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
