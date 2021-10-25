<?php

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\Handlers\Filters\RangeFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\GeoModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Illuminate\Http\Request;
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;

/**
 * @group elastic
 * @group filter
 * @group elastic-filter
 */
class RangeFilterTest extends TestCase
{
    /** @var Collection */
    protected $models;

    public function setUp(): void
    {
        parent::setUp();

        $this->models = factory(TestModel::class, 5)->create();
    }

    /** @test */
    public function it_can_filter_by_lt_and_rt(): void
    {
        $modelsResult = $this
            ->createQueryFromFilterRequest([
                'id' => [
                    'gt' => '2',
                    'lte' => '4'
                ],
            ])
            ->setAllowedFilters(new RangeFilter('id'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $modelsResult);
        $this->assertEqualsCanonicalizing([3,4], $modelsResult->pluck('id')->all());
    }

    /** @test */
    public function it_allows_empty_filter_value(): void
    {
        $modelsResult = $this
            ->createQueryFromFilterRequest([
                'id' => ''
            ])
            ->setAllowedFilters(new RangeFilter('id'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(5, $modelsResult);
    }

    /** @test */
    public function it_should_apply_a_default_filter_value_if_nothing_in_request(): void
    {
        $filter = (new RangeFilter('id'))->default(['gte' => '2', 'lt' => 4]);

        $modelsResult = $this
            ->createQueryFromFilterRequest([])
            ->setAllowedFilters($filter)
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $modelsResult);
        $this->assertEqualsCanonicalizing([2,3], $modelsResult->pluck('id')->all());
    }

    /** @test */
    public function it_does_not_apply_default_filter_when_filter_exists_and_default_is_set(): void
    {
        $filter = (new RangeFilter('id'))->default(['gte' => '2', 'lt' => 4]);

        $modelsResult = $this
            ->createQueryFromFilterRequest([
                'id' => [
                    'gt' => 3,
                    'lte' => '5'
                ],
            ])
            ->setAllowedFilters($filter)
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $modelsResult);
        $this->assertEqualsCanonicalizing([4,5], $modelsResult->pluck('id')->all());
    }

    protected function createQueryFromFilterRequest(array $filters): ElasticQueryWizard
    {
        $request = new Request([
            'filter' => $filters,
        ]);

        return ElasticQueryWizard::for(TestModel::class, $request);
    }
}
