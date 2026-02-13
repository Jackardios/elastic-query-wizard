<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit;

use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\QueryWizard\QueryParametersManager;
use Mockery;

/**
 * Tests for request-scope lifecycle handling.
 *
 * @group unit
 */
class RequestScopeTest extends UnitTestCase
{
    /** @test */
    public function wizard_created_without_parameters_resolves_from_container(): void
    {
        // First manager
        $firstManager = new QueryParametersManager();
        $this->app->instance(QueryParametersManager::class, $firstManager);

        $wizard = ElasticQueryWizard::for(TestModel::class);

        // Replace manager in container
        $secondManager = new QueryParametersManager();
        $this->app->instance(QueryParametersManager::class, $secondManager);

        // getParametersManager should return the fresh manager
        $resolvedManager = $wizard->getParametersManager();

        $this->assertSame($secondManager, $resolvedManager);
    }

    /** @test */
    public function wizard_created_with_explicit_parameters_keeps_original(): void
    {
        $explicitManager = new QueryParametersManager();

        $wizard = ElasticQueryWizard::for(TestModel::class, $explicitManager);

        // Replace manager in container
        $containerManager = new QueryParametersManager();
        $this->app->instance(QueryParametersManager::class, $containerManager);

        // getParametersManager should return the original explicit manager
        $resolvedManager = $wizard->getParametersManager();

        $this->assertSame($explicitManager, $resolvedManager);
        $this->assertNotSame($containerManager, $resolvedManager);
    }

    /** @test */
    public function forSchema_wizard_uses_container_manager_at_creation_time(): void
    {
        // forSchema passes explicit manager from container at creation time,
        // so it doesn't resolve from container on subsequent calls.
        // This is intentional - forSchema is a convenience method that
        // captures the manager at creation time.

        // First manager
        $firstManager = new QueryParametersManager();
        $this->app->instance(QueryParametersManager::class, $firstManager);

        // Create a simple schema mock
        $schema = Mockery::mock(\Jackardios\QueryWizard\Schema\ResourceSchemaInterface::class);
        $schema->shouldReceive('model')->andReturn(TestModel::class);
        $schema->shouldReceive('type')->andReturn('testModel');
        $schema->shouldReceive('filters')->andReturn([]);
        $schema->shouldReceive('sorts')->andReturn([]);
        $schema->shouldReceive('includes')->andReturn([]);
        $schema->shouldReceive('fields')->andReturn([]);
        $schema->shouldReceive('appends')->andReturn([]);
        $schema->shouldReceive('defaultSorts')->andReturn([]);
        $schema->shouldReceive('defaultIncludes')->andReturn([]);
        $schema->shouldReceive('defaultFields')->andReturn([]);
        $schema->shouldReceive('defaultAppends')->andReturn([]);
        $schema->shouldReceive('defaultFilters')->andReturn([]);

        $wizard = ElasticQueryWizard::forSchema($schema);

        // getParametersManager should return the manager that was in container at creation time
        $resolvedManager = $wizard->getParametersManager();

        $this->assertSame($firstManager, $resolvedManager);
    }
}
