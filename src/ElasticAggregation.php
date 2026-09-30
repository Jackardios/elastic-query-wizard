<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard;

use BadMethodCallException;
use Jackardios\EsScoutDriver\Aggregations\Agg;
use Jackardios\EsScoutDriver\Aggregations\Bucket\CompositeAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\DateHistogramAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\FilterAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\FiltersAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\GeoDistanceAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\GlobalAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\HistogramAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\NestedAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\RangeAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\ReverseNestedAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\TermsAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\AvgAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\CardinalityAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\ExtendedStatsAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\GeoBoundsAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\GeoCentroidAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\MaxAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\MinAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\PercentilesAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\StatsAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\SumAggregation;
use Jackardios\EsScoutDriver\Aggregations\Metric\TopHitsAggregation;
use Jackardios\EsScoutDriver\Query\QueryInterface;

/**
 * Proxy for es-scout-driver aggregation factory inside elastic-query-wizard namespace.
 *
 * Forwards every factory of `Jackardios\EsScoutDriver\Aggregations\Agg` and its macros.
 *
 * @method static TermsAggregation terms(string $field)
 * @method static AvgAggregation avg(string $field)
 * @method static SumAggregation sum(string $field)
 * @method static MinAggregation min(string $field)
 * @method static MaxAggregation max(string $field)
 * @method static StatsAggregation stats(string $field)
 * @method static CardinalityAggregation cardinality(string $field)
 * @method static HistogramAggregation histogram(string $field, int|float $interval)
 * @method static DateHistogramAggregation dateHistogram(string $field, string $calendarInterval)
 * @method static RangeAggregation range(string $field)
 * @method static PercentilesAggregation percentiles(string $field)
 * @method static ExtendedStatsAggregation extendedStats(string $field)
 * @method static TopHitsAggregation topHits()
 * @method static CompositeAggregation composite()
 * @method static FilterAggregation filter(QueryInterface|array<string, mixed> $filter)
 * @method static FiltersAggregation filters()
 * @method static GlobalAggregation global()
 * @method static NestedAggregation nested(string $path)
 * @method static ReverseNestedAggregation reverseNested()
 * @method static GeoDistanceAggregation geoDistance(string $field, float $lat, float $lon)
 * @method static GeoBoundsAggregation geoBounds(string $field)
 * @method static GeoCentroidAggregation geoCentroid(string $field)
 */
final class ElasticAggregation
{
    /**
     * @param  array<int|string, mixed>  $arguments
     *
     * @throws BadMethodCallException When Agg has no such factory or macro
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        if (! method_exists(Agg::class, $name) && ! Agg::hasMacro($name)) {
            throw new BadMethodCallException(sprintf('Call to undefined method %s::%s()', self::class, $name));
        }

        return Agg::$name(...$arguments);
    }
}
