<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\Filters\IdsFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;

/**
 * @group elastic
 * @group filter
 * @group elastic-filter
 */
class IdsFilterTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = TestModel::factory()->count(5)->create();
    }

    /** @test */
    public function it_can_filter_by_single_id(): void
    {
        $targetId = $this->models[0]->id;

        $result = $this
            ->createElasticWizardWithFilters(['ids' => (string) $targetId])
            ->allowedFilters(IdsFilter::make('id', 'ids'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $result);
        $this->assertEquals($targetId, $result->first()->id);
    }

    /** @test */
    public function it_can_filter_by_multiple_ids(): void
    {
        $targetIds = [$this->models[0]->id, $this->models[2]->id, $this->models[4]->id];

        $result = $this
            ->createElasticWizardWithFilters(['ids' => implode(',', $targetIds)])
            ->allowedFilters(IdsFilter::make('id', 'ids'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $result);
        $this->assertEqualsCanonicalizing($targetIds, $result->pluck('id')->all());
    }

    /** @test */
    public function it_can_filter_by_ids_array(): void
    {
        $targetIds = [$this->models[1]->id, $this->models[3]->id];

        $result = $this
            ->createElasticWizardWithFilters(['ids' => $targetIds])
            ->allowedFilters(IdsFilter::make('id', 'ids'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $result);
        $this->assertEqualsCanonicalizing($targetIds, $result->pluck('id')->all());
    }

    /** @test */
    public function it_returns_no_results_for_non_existing_ids(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['ids' => '99999,99998'])
            ->allowedFilters(IdsFilter::make('id', 'ids'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(0, $result);
    }

    /** @test */
    public function it_allows_empty_filter_value(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['ids' => ''])
            ->allowedFilters(IdsFilter::make('id', 'ids'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(5, $result);
    }

    /** @test */
    public function it_filters_out_blank_values_from_array(): void
    {
        $targetId = $this->models[0]->id;

        $result = $this
            ->createElasticWizardWithFilters(['ids' => [$targetId, '', null]])
            ->allowedFilters(IdsFilter::make('id', 'ids'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $result);
        $this->assertEquals($targetId, $result->first()->id);
    }
}
