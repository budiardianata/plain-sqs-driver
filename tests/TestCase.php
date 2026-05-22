<?php

declare(strict_types=1);

namespace Budiardianata\PlainSqsDriver\Tests;

use Budiardianata\PlainSqsDriver\PlainSqsDriverServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

    }

    public function getEnvironmentSetUp($app)
    {
        //        config()->set('database.default', 'testing');

        /*
         foreach (\Illuminate\Support\Facades\File::allFiles(__DIR__ . '/../database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
         }
         */
    }

    protected function getPackageProviders($app)
    {
        return [
            PlainSqsDriverServiceProvider::class,
        ];
    }
}
