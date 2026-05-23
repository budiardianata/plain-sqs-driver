<?php

declare(strict_types=1);

namespace Budiardianata\PlainSqsDriver\Job;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PlainSqsDispatcherJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected bool $plain = false;

    public function __construct(protected mixed $data) {}

    public function __invoke(): void
    {
        $this->getPayload();
    }

    public function getPayload(): mixed
    {
        if (! $this->isPlain()) {
            return [
                'job' => app('config')->get('plain-sqs.default-handler'),
                'data' => $this->data,
            ];
        }

        return $this->data;
    }

    /**
     * @return $this
     */
    public function setPlain(bool $plain = true): self
    {
        $this->plain = $plain;

        return $this;
    }

    public function isPlain(): bool
    {
        return $this->plain;
    }
}
