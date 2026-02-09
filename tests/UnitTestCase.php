<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests;

use Elastic\Migrations\ServiceProvider as ElasticMigrationsServiceProvider;
use Jackardios\ElasticQueryWizard\Tests\Concerns\AssertsElasticQuery;
use Jackardios\ElasticQueryWizard\Tests\Concerns\QueryWizardTestingHelpers;
use Jackardios\EsScoutDriver\ServiceProvider as EsScoutDriverServiceProvider;
use Jackardios\QueryWizard\QueryWizardServiceProvider;
use Laravel\Scout\ScoutServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class UnitTestCase extends Orchestra
{
    use QueryWizardTestingHelpers;
    use AssertsElasticQuery;

    protected function getPackageProviders($app): array
    {
        return [
            QueryWizardServiceProvider::class,
            ScoutServiceProvider::class,
            ElasticMigrationsServiceProvider::class,
            EsScoutDriverServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('scout.driver', 'elastic');
    }
}
