<?php

namespace Jackardios\ElasticQueryWizard\Tests;

use ElasticClient\ServiceProvider as ElasticClientServiceProvider;
use ElasticMigrations\ServiceProvider as ElasticMigrationsServiceProvider;
use ElasticScoutDriver\ServiceProvider as ElasticScoutDriverServiceProvider;
use ElasticScoutDriverPlus\ServiceProvider as ElasticScoutDriverPlusServiceProvider;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Jackardios\ElasticQueryWizard\Tests\Concerns\AssertsModels;
use Jackardios\ElasticQueryWizard\Tests\Concerns\AssertsQueryLog;
use Jackardios\QueryWizard\QueryWizardServiceProvider;
use Laravel\Scout\ScoutServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
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
            ElasticScoutDriverServiceProvider::class,
            ElasticScoutDriverPlusServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('scout.driver', 'elastic');
        $app['config']->set('elastic.migrations.storage_directory', __DIR__ . '/App/data/elastic/migrations');
        $app['config']->set('elastic.scout_driver.refresh_documents', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/App/data/migrations');
        $this->withFactories(__DIR__ . '/App/data/factories');

        $this->artisan('migrate')->run();
        $this->artisan('elastic:migrate')->run();
    }

    protected function tearDown(): void
    {
        $this->artisan('elastic:migrate:reset')->run();
        $this->artisan('migrate:reset')->run();

        parent::tearDown();
    }
}
