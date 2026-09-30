<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests;

use Elastic\Client\ServiceProvider as ElasticClientServiceProvider;
use Elastic\Elasticsearch\Client;
use Elastic\Migrations\ServiceProvider as ElasticMigrationsServiceProvider;
use Illuminate\Foundation\Application;
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
    use AssertsModels;
    use AssertsQueryLog;
    use DatabaseMigrations;
    use QueryWizardTestingHelpers;

    /**
     * Indices the Elasticsearch migrations of the fixtures create.
     */
    private const INDICES = [
        'append_models',
        'geo_models',
        'morph_models',
        'nested_models',
        'scope_models',
        'soft_delete_models',
        'test_models',
    ];

    private static bool $indicesCreated = false;

    /**
     * @param  Application  $app
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

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('scout.driver', 'elastic');
        $app['config']->set('elastic.migrations.storage.default_path', __DIR__.'/Fixtures/data/elastic/migrations');
        $app['config']->set('elastic.scout.refresh_documents', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/Fixtures/data/migrations');

        $this->prepareIndices();
    }

    /**
     * Create the indices once per process and empty them before every other test:
     * recreating them for each test made up most of the suite's run time.
     */
    private function prepareIndices(): void
    {
        $client = $this->app->make(Client::class);
        $indices = implode(',', self::INDICES);

        if (self::$indicesCreated) {
            $client->deleteByQuery([
                'index' => $indices,
                'refresh' => true,
                'conflicts' => 'proceed',
                'body' => ['query' => ['match_all' => new \stdClass]],
            ]);

            return;
        }

        $client->indices()->delete(['index' => $indices, 'ignore_unavailable' => true]);
        $this->artisan('elastic:migrate')->run();
        self::$indicesCreated = true;
    }
}
