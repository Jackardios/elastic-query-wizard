<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests;

use Elastic\Client\ServiceProvider as ElasticClientServiceProvider;
use Elastic\Migrations\ServiceProvider as ElasticMigrationsServiceProvider;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Jackardios\ElasticQueryWizard\Tests\Concerns\AssertsModels;
use Jackardios\ElasticQueryWizard\Tests\Concerns\AssertsQueryLog;
use Jackardios\ElasticQueryWizard\Tests\Concerns\QueryWizardTestingHelpers;
use Jackardios\EsScoutDriver\ServiceProvider as EsScoutDriverServiceProvider;
use Jackardios\QueryWizard\QueryWizardServiceProvider;
use Laravel\Scout\ScoutServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use QueryWizardTestingHelpers;
    use DatabaseMigrations;
    use AssertsQueryLog;
    use AssertsModels;

    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app): array
    {
        return [
            QueryWizardServiceProvider::class,
            ScoutServiceProvider::class,
            ElasticClientServiceProvider::class,
            ElasticMigrationsServiceProvider::class,
            EsScoutDriverServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('scout.driver', 'elastic');
        $app['config']->set('elastic.migrations.storage.default_path', __DIR__ . '/Fixtures/data/elastic/migrations');
        $app['config']->set('elastic.scout.refresh_documents', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/Fixtures/data/migrations');

        $this->artisan('elastic:migrate')->run();
    }

    protected function tearDown(): void
    {
        $this->artisan('elastic:migrate:reset')->run();

        parent::tearDown();
    }
}
