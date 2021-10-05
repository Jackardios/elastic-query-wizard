<?php

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use ElasticScoutDriverPlus\Support\Query;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Illuminate\Http\Request;
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\Handlers\ElasticQueryHandler;
use Jackardios\ElasticQueryWizard\Handlers\Filters\CallbackFilter;
use Jackardios\ElasticQueryWizard\Tests\App\Models\TestModel;

/**
 * @group elastic
 * @group filter
 * @group elastic-filter
 */
class CallbackFilterTest extends TestCase
{
    /** @var \Illuminate\Support\Collection */
    protected $models;

    public function setUp(): void
    {
        parent::setUp();

        $this->models = factory(TestModel::class, 3)->create();
    }

    /** @test */
    public function it_should_filter_by_closure(): void
    {
        $expectedModel = factory(TestModel::class)->create(['name' => 'Some New Testing Name']);
        $models = $this
            ->createQueryFromFilterRequest([
                'callback' => $expectedModel->name
            ])
            ->setAllowedFilters(
                new CallbackFilter('callback', function (ElasticQueryHandler $queryHandler, $queryBuilder, $value) {
                    $queryHandler->getMainBoolQuery()->must(
                        Query::match()->field('name')->query($value)
                    );
                })
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $models);
        $this->assertEquals($expectedModel->name, $models->first()->name);
    }

    /** @test */
    public function it_should_filter_by_array_callback(): void
    {
        $expectedModel = factory(TestModel::class)->create(['name' => 'Some New Testing Name']);
        $models = $this
            ->createQueryFromFilterRequest([
                'callback' => $expectedModel->name,
            ])
            ->setAllowedFilters(new CallbackFilter('callback', [$this, 'filterCallback']))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $models);
        $this->assertEquals($expectedModel->name, $models->first()->name);
    }

    public function filterCallback(ElasticQueryHandler $queryHandler, $queryBuilder, $value): void
    {
        $queryHandler->getMainBoolQuery()->must(
            Query::match()->field('name')->query($value)
        );
    }

    protected function createQueryFromFilterRequest(array $filters): ElasticQueryWizard
    {
        $request = new Request([
            'filter' => $filters,
        ]);

        return ElasticQueryWizard::for(TestModel::class, $request);
    }
}
