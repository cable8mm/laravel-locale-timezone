<?php

namespace Cable8mm\RequestContext\Tests;

use Cable8mm\RequestContext\RequestContextServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            RequestContextServiceProvider::class,
        ];
    }
}
