<?php

declare(strict_types=1);

namespace Budiardianata\PlainSqsDriver;

use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Queue;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class PlainSqsDriverServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('plain-sqs-driver')->hasConfigFile('plain-sqs');
    }

    public function registerPackageConfigs(): self
    {
        $this->app->booted(function () {
            $this->app['queue']->extend('sqs-plain', function () {
                return new PlainSqsConnector;
            });
        });

        return $this;
    }

    public function bootingPackage(): void
    {
        Queue::after(fn (JobProcessed $event) => $event->job->delete());
    }
}
