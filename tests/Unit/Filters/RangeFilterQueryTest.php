<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\Exceptions\InvalidRangeValue;
use Jackardios\ElasticQueryWizard\Filters\RangeFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;

/**
 * @group unit
 * @group filter
 */
class RangeFilterQueryTest extends UnitTestCase
{
    /** @test */
    public function it_builds_a_range_query(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['age' => ['gte' => '18', 'lte' => '65']])
            ->allowedFilters(RangeFilter::make('age'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['range' => ['age' => ['gte' => '18', 'lte' => '65']]], $queries[0]);
    }

    /** @test */
    public function it_does_not_add_a_query_for_empty_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['age' => []])
            ->allowedFilters(RangeFilter::make('age'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    /** @test */
    public function it_throws_for_invalid_range_keys(): void
    {
        $this->expectException(InvalidRangeValue::class);

        $this
            ->createElasticWizardWithFilters(['age' => ['invalid_key' => '18']])
            ->allowedFilters(RangeFilter::make('age'))
            ->build();
    }

    /** @test */
    public function it_supports_single_bound(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['age' => ['gt' => '10']])
            ->allowedFilters(RangeFilter::make('age'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['range' => ['age' => ['gt' => '10']]], $queries[0]);
    }
}
