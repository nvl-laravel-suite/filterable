<?php

declare(strict_types=1);

namespace Nvl\Filterable\Tests;

use Nvl\Data\Providers\DataServiceProvider;
use Nvl\Filterable\Providers\FilterableServiceProvider;
use Nvl\Support\Providers\LocaleServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Boots Filterable in an isolated Laravel application.
 */
abstract class TestCase extends Orchestra
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LocaleServiceProvider::class,
            DataServiceProvider::class,
            FilterableServiceProvider::class,
        ];
    }
}
