<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\Filters\DateRangeFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
#[Group('filter')]
class DateRangeFilterQueryTest extends UnitTestCase
{
    #[Test]
    public function it_adds_a_range_filter_with_from_and_to(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['date' => ['from' => '2024-01-01', 'to' => '2024-12-31']])
            ->allowedFilters(DateRangeFilter::make('created_at', 'date'));
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $filterQueries);
        $this->assertEquals([
            'range' => [
                'created_at' => [
                    'gte' => '2024-01-01',
                    'lte' => '2024-12-31',
                ],
            ],
        ], $filterQueries[0]);
    }

    #[Test]
    public function it_adds_a_range_filter_with_only_from(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['date' => ['from' => '2024-01-01']])
            ->allowedFilters(DateRangeFilter::make('created_at', 'date'));
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $filterQueries);
        $this->assertEquals([
            'range' => [
                'created_at' => [
                    'gte' => '2024-01-01',
                ],
            ],
        ], $filterQueries[0]);
    }

    #[Test]
    public function it_adds_a_range_filter_with_only_to(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['date' => ['to' => '2024-12-31']])
            ->allowedFilters(DateRangeFilter::make('created_at', 'date'));
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $filterQueries);
        $this->assertEquals([
            'range' => [
                'created_at' => [
                    'lte' => '2024-12-31',
                ],
            ],
        ], $filterQueries[0]);
    }

    #[Test]
    public function it_ignores_an_empty_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['date' => []])
            ->allowedFilters(DateRangeFilter::make('created_at', 'date'));
        $wizard->build();

        $this->assertEmpty($this->getFilterQueries($wizard->boolQuery()));
    }

    #[Test]
    public function it_rejects_an_array_without_any_known_bound(): void
    {
        // Carries data, but none of it addresses a bound - silently matching
        // everything would be worse than saying so.
        $this->expectException(InvalidFilterQuery::class);

        $this
            ->createElasticWizardWithFilters(['date' => ['unknown' => '2024-01-01']])
            ->allowedFilters(DateRangeFilter::make('created_at', 'date'))
            ->build();
    }

    #[Test]
    public function it_rejects_a_non_array_value(): void
    {
        $this->expectException(InvalidFilterQuery::class);

        $this
            ->createElasticWizardWithFilters(['date' => 'not-an-array'])
            ->allowedFilters(DateRangeFilter::make('created_at', 'date'))
            ->build();
    }

    #[Test]
    public function it_uses_custom_from_and_to_keys(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['date' => ['start' => '2024-01-01', 'end' => '2024-12-31']])
            ->allowedFilters(
                DateRangeFilter::make('created_at', 'date')
                    ->fromKey('start')
                    ->toKey('end')
            );
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $filterQueries);
        $this->assertEquals([
            'range' => [
                'created_at' => [
                    'gte' => '2024-01-01',
                    'lte' => '2024-12-31',
                ],
            ],
        ], $filterQueries[0]);
    }

    #[Test]
    public function it_adds_format_parameter(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['date' => ['from' => '01/01/2024']])
            ->allowedFilters(
                DateRangeFilter::make('created_at', 'date')
                    ->dateFormat('dd/MM/yyyy')
            );
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $filterQueries);
        $this->assertEquals([
            'range' => [
                'created_at' => [
                    'gte' => '01/01/2024',
                    'format' => 'dd/MM/yyyy',
                ],
            ],
        ], $filterQueries[0]);
    }

    #[Test]
    public function it_adds_timezone_parameter(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['date' => ['from' => '2024-01-01']])
            ->allowedFilters(
                DateRangeFilter::make('created_at', 'date')
                    ->timezone('Europe/Moscow')
            );
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $filterQueries);
        $this->assertEquals([
            'range' => [
                'created_at' => [
                    'gte' => '2024-01-01',
                    'time_zone' => 'Europe/Moscow',
                ],
            ],
        ], $filterQueries[0]);
    }

    #[Test]
    public function it_ignores_empty_string_values(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['date' => ['from' => '', 'to' => '']])
            ->allowedFilters(DateRangeFilter::make('created_at', 'date'));
        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertEmpty($filterQueries);
    }

    #[Test]
    public function it_returns_correct_type(): void
    {
        $filter = DateRangeFilter::make('created_at', 'date');

        $this->assertEquals('date_range', $filter->getType());
    }
}
