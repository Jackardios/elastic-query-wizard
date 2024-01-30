<?php

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Elastic\ScoutDriverPlus\Support\Query;
use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\Filters\CallbackFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;

/**
 * @group elastic
 * @group filter
 * @group elastic-filter
 */
class CallbackFilterTest extends TestCase
{
    protected Collection $models;

    public function setUp(): void
    {
        parent::setUp();

        $this->models = factory(TestModel::class, 3)->create();
    }

    /** @test */
    public function it_should_filter_by_closure(): void
    {
        $expectedModel = factory(TestModel::class)->create(['name' => 'Some New Testing Name']);
        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'callback' => $expectedModel->name
            ])
            ->setAllowedFilters(
                new CallbackFilter('callback', function (ElasticQueryWizard $queryWizard, $queryBuilder, $value) {
                    $queryWizard->getRootBoolQuery()->must(
                        Query::match()->field('name')->query($value)
                    );
                })
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $modelsResult);
        $this->assertEquals($expectedModel->name, $modelsResult->first()->name);
    }

    /** @test */
    public function it_should_filter_by_array_callback(): void
    {
        $expectedModel = factory(TestModel::class)->create(['name' => 'Some New Testing Name']);
        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'callback' => $expectedModel->name,
            ])
            ->setAllowedFilters(new CallbackFilter('callback', [$this, 'filterCallback']))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $modelsResult);
        $this->assertEquals($expectedModel->name, $modelsResult->first()->name);
    }

    public function filterCallback(ElasticQueryWizard $queryWizard, $queryBuilder, $value): void
    {
        $queryWizard->getRootBoolQuery()->must(
            Query::match()->field('name')->query($value)
        );
    }
}
