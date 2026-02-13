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
    public function it_throws_for_zero_string_scalar_value(): void
    {
        $this->expectException(InvalidRangeValue::class);

        $this
            ->createElasticWizardWithFilters(['age' => '0'])
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

    /** @test */
    public function it_throws_for_legacy_from_operator(): void
    {
        $this->expectException(InvalidRangeValue::class);
        $this->expectExceptionMessage('uses legacy operator `from` which was removed in Elasticsearch 9.x. Use `gte` instead');

        $this
            ->createElasticWizardWithFilters(['age' => ['from' => '18']])
            ->allowedFilters(RangeFilter::make('age'))
            ->build();
    }

    /** @test */
    public function it_throws_for_legacy_to_operator(): void
    {
        $this->expectException(InvalidRangeValue::class);
        $this->expectExceptionMessage('uses legacy operator `to` which was removed in Elasticsearch 9.x. Use `lte` instead');

        $this
            ->createElasticWizardWithFilters(['age' => ['to' => '65']])
            ->allowedFilters(RangeFilter::make('age'))
            ->build();
    }

    /** @test */
    public function it_throws_for_legacy_include_lower_operator(): void
    {
        $this->expectException(InvalidRangeValue::class);
        $this->expectExceptionMessage('legacy operator `include_lower`');

        $this
            ->createElasticWizardWithFilters(['age' => ['include_lower' => true]])
            ->allowedFilters(RangeFilter::make('age'))
            ->build();
    }

    /** @test */
    public function it_throws_for_legacy_include_upper_operator(): void
    {
        $this->expectException(InvalidRangeValue::class);
        $this->expectExceptionMessage('legacy operator `include_upper`');

        $this
            ->createElasticWizardWithFilters(['age' => ['include_upper' => true]])
            ->allowedFilters(RangeFilter::make('age'))
            ->build();
    }
}
