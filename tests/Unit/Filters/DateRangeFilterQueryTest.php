<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\Filters\DateRangeFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use PHPUnit\Framework\Attributes\DataProvider;
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
                    'gte' => '2024-01-01T00:00:00+00:00',
                    'lt' => '2025-01-01T00:00:00+00:00',
                    'format' => 'strict_date_optional_time',
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
                    'gte' => '2024-01-01T00:00:00+00:00',
                    'format' => 'strict_date_optional_time',
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
                    'lt' => '2025-01-01T00:00:00+00:00',
                    'format' => 'strict_date_optional_time',
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
                    'gte' => '2024-01-01T00:00:00+00:00',
                    'lt' => '2025-01-01T00:00:00+00:00',
                    'format' => 'strict_date_optional_time',
                ],
            ],
        ], $filterQueries[0]);
    }

    #[Test]
    public function es_format_is_tried_before_the_iso_format_the_bounds_are_sent_in(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['date' => ['from' => '2024-01-01']])
            ->allowedFilters(DateRangeFilter::make('created_at', 'date')->esFormat('strict_date_time_no_millis'));
        $wizard->build();

        $this->assertSame(
            ['gte' => '2024-01-01T00:00:00+00:00', 'format' => 'strict_date_time_no_millis||strict_date_optional_time'],
            $this->getFilterQueries($wizard->boolQuery())[0]['range']['created_at']
        );
    }

    #[Test]
    public function an_es_format_that_already_lists_the_iso_format_is_sent_as_given(): void
    {
        foreach (['strict_date_optional_time', 'yyyy-MM-dd||strict_date_optional_time'] as $format) {
            $wizard = $this
                ->createElasticWizardWithFilters(['date' => ['from' => '2024-01-01']])
                ->allowedFilters(DateRangeFilter::make('created_at', 'date')->esFormat($format));
            $wizard->build();

            $this->assertSame($format, $this->getFilterQueries($wizard->boolQuery())[0]['range']['created_at']['format']);
        }
    }

    #[Test]
    public function bounds_without_an_offset_are_read_in_the_filter_timezone(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['date' => ['from' => '2024-01-01', 'to' => '2024-01-31T18:00']])
            ->allowedFilters(DateRangeFilter::make('created_at', 'date')->timezone('Europe/Moscow'));
        $wizard->build();

        $this->assertSame(
            ['gte' => '2024-01-01T00:00:00+03:00', 'lte' => '2024-01-31T18:00:00+03:00', 'format' => 'strict_date_optional_time'],
            $this->getFilterQueries($wizard->boolQuery())[0]['range']['created_at']
        );
    }

    #[Test]
    public function bounds_are_read_in_the_application_timezone_by_default(): void
    {
        $default = date_default_timezone_get();
        date_default_timezone_set('Asia/Tokyo');

        try {
            $wizard = $this
                ->createElasticWizardWithFilters(['date' => ['from' => '2024-01-01', 'to' => '2024-01-31T10:00:00Z']])
                ->allowedFilters(DateRangeFilter::make('created_at', 'date'));
            $wizard->build();
        } finally {
            date_default_timezone_set($default);
        }

        $this->assertSame(
            ['gte' => '2024-01-01T00:00:00+09:00', 'lte' => '2024-01-31T19:00:00+09:00', 'format' => 'strict_date_optional_time'],
            $this->getFilterQueries($wizard->boolQuery())[0]['range']['created_at']
        );
    }

    #[Test]
    public function a_date_to_bound_covers_the_whole_day(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['date' => ['to' => '9999-12-31']])
            ->allowedFilters(DateRangeFilter::make('created_at', 'date'));
        $wizard->build();

        $this->assertSame(
            ['lte' => '9999-12-31T23:59:59+00:00', 'format' => 'strict_date_optional_time'],
            $this->getFilterQueries($wizard->boolQuery())[0]['range']['created_at']
        );
    }

    #[Test]
    public function a_default_bound_may_be_a_date_time_object(): void
    {
        $wizard = $this
            ->createElasticWizardFromQuery()
            ->allowedFilters(DateRangeFilter::make('created_at', 'date')->default([
                'from' => new \DateTimeImmutable('2024-01-01 10:30:00.250000', new \DateTimeZone('UTC')),
            ]));
        $wizard->build();

        $this->assertSame(
            ['gte' => '2024-01-01T10:30:00.250000+00:00', 'format' => 'strict_date_optional_time'],
            $this->getFilterQueries($wizard->boolQuery())[0]['range']['created_at']
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unreadableBounds(): array
    {
        return [
            'text' => ['abc'],
            'epoch seconds' => ['1700000000'],
            'year' => ['2024'],
            'date math' => ['now-1d'],
            'other format' => ['01/01/2024'],
        ];
    }

    #[Test]
    #[DataProvider('unreadableBounds')]
    public function a_bound_that_is_not_an_iso_date_is_a_400(string $bound): void
    {
        $this->expectException(InvalidFilterValue::class);
        $this->expectExceptionMessage('Expected a date (Y-m-d) or an ISO 8601 date-time for `from`');

        $this
            ->createElasticWizardWithFilters(['date' => ['from' => $bound]])
            ->allowedFilters(DateRangeFilter::make('created_at', 'date'))
            ->build();
    }

    #[Test]
    public function an_unknown_timezone_is_refused_when_configured(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown timezone `Mars/Base`');

        DateRangeFilter::make('created_at')->timezone('Mars/Base');
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
    public function a_key_other_than_the_bounds_is_a_400(): void
    {
        $this->expectException(InvalidFilterQuery::class);
        $this->expectExceptionMessage('expects only the `from` and `to` keys');

        $this
            ->createElasticWizardWithFilters(['date' => ['form' => '2024-01-01', 'to' => '2024-01-31']])
            ->allowedFilters(DateRangeFilter::make('created_at', 'date'))
            ->build();
    }

    #[Test]
    public function a_bound_that_is_a_list_is_a_400(): void
    {
        try {
            $this
                ->createElasticWizardWithFilters(['date' => ['from' => ['2024-01-01', '2024-02-01']]])
                ->allowedFilters(DateRangeFilter::make('created_at', 'date'))
                ->build();
            $this->fail('A list bound was accepted.');
        } catch (InvalidFilterQuery $exception) {
            $this->assertSame(400, $exception->getStatusCode());
            $this->assertStringContainsString('Filter `date` expects a scalar value for `from`.', $exception->getMessage());
        }
    }

    #[Test]
    public function a_bound_past_the_year_9999_in_the_filter_timezone_is_a_400(): void
    {
        $this->expectException(InvalidFilterValue::class);
        $this->expectExceptionMessage('Expected a date before the year 10000 for `from`');

        $this
            ->createElasticWizardWithFilters(['date' => ['from' => '9999-12-31T23:59:59-12:00']])
            ->allowedFilters(DateRangeFilter::make('created_at', 'date'))
            ->build();
    }

    #[Test]
    public function the_parameters_the_filter_sets_itself_are_refused(): void
    {
        foreach (['format' => 'esFormat()', 'gte' => 'the request', 'time_zone' => 'timezone()'] as $name => $instead) {
            try {
                DateRangeFilter::make('created_at')->withParameters([$name => 'x']);
                $this->fail("withParameters() accepted {$name}");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString("Parameter `{$name}` is set by", $e->getMessage());
                $this->assertStringContainsString($instead, $e->getMessage());
            }
        }
    }
}
