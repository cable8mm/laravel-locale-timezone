<?php

namespace Cable8mm\LocaleTimezone\Tests;

use Cable8mm\LocaleTimezone\LocaleTimezoneServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LocaleTimezoneServiceProvider::class,
        ];
    }
}
