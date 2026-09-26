<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\Exceptions\InvalidRangeValue;
use Jackardios\ElasticQueryWizard\Filters\RangeFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
#[Group('filter')]
class RangeFilterQueryTest extends UnitTestCase
{
    #[Test]
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

    #[Test]
    public function it_does_not_add_a_query_for_empty_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['age' => []])
            ->allowedFilters(RangeFilter::make('age'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    #[Test]
    public function it_throws_for_invalid_range_keys(): void
    {
        $this->expectException(InvalidRangeValue::class);

        $this
            ->createElasticWizardWithFilters(['age' => ['invalid_key' => '18']])
            ->allowedFilters(RangeFilter::make('age'))
            ->build();
    }

    #[Test]
    public function it_throws_for_zero_string_scalar_value(): void
    {
        $this->expectException(InvalidRangeValue::class);

        $this
            ->createElasticWizardWithFilters(['age' => '0'])
            ->allowedFilters(RangeFilter::make('age'))
            ->build();
    }

    #[Test]
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

    #[Test]
    public function it_throws_for_legacy_from_operator(): void
    {
        $this->expectException(InvalidRangeValue::class);
        $this->expectExceptionMessage('The `from` operator was removed in Elasticsearch 9.x. Use `gte` instead');

        $this
            ->createElasticWizardWithFilters(['age' => ['from' => '18']])
            ->allowedFilters(RangeFilter::make('age'))
            ->build();
    }

    #[Test]
    public function it_throws_for_legacy_to_operator(): void
    {
        $this->expectException(InvalidRangeValue::class);
        $this->expectExceptionMessage('The `to` operator was removed in Elasticsearch 9.x. Use `lte` instead');

        $this
            ->createElasticWizardWithFilters(['age' => ['to' => '65']])
            ->allowedFilters(RangeFilter::make('age'))
            ->build();
    }

    #[Test]
    public function it_throws_for_legacy_include_lower_operator(): void
    {
        $this->expectException(InvalidRangeValue::class);
        $this->expectExceptionMessage('The `include_lower` operator was removed');

        $this
            ->createElasticWizardWithFilters(['age' => ['include_lower' => true]])
            ->allowedFilters(RangeFilter::make('age'))
            ->build();
    }

    #[Test]
    public function it_throws_for_legacy_include_upper_operator(): void
    {
        $this->expectException(InvalidRangeValue::class);
        $this->expectExceptionMessage('The `include_upper` operator was removed');

        $this
            ->createElasticWizardWithFilters(['age' => ['include_upper' => true]])
            ->allowedFilters(RangeFilter::make('age'))
            ->build();
    }

    #[Test]
    public function an_empty_bound_is_no_bound(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['price' => ['gte' => '', 'lte' => '5']])
            ->allowedFilters(RangeFilter::make('price'));
        $wizard->build();

        $this->assertEquals([['range' => ['price' => ['lte' => '5']]]], $this->getFilterQueries($wizard->boolQuery()));
    }

    #[Test]
    public function only_empty_bounds_apply_no_range(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['price' => ['gte' => '', 'lte' => ' ']])
            ->allowedFilters(RangeFilter::make('price'));
        $wizard->build();

        $this->assertSame([], $this->getFilterQueries($wizard->boolQuery()));
    }
}
