<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard;

use BadMethodCallException;
use Jackardios\EsScoutDriver\Aggregations\Agg;

/**
 * Proxy for es-scout-driver aggregation factory inside elastic-query-wizard namespace.
 *
 * @method static mixed terms(string $field)
 * @method static mixed avg(string $field)
 * @method static mixed sum(string $field)
 * @method static mixed min(string $field)
 * @method static mixed max(string $field)
 * @method static mixed stats(string $field)
 * @method static mixed cardinality(string $field)
 * @method static mixed histogram(string $field, int|float $interval)
 * @method static mixed dateHistogram(string $field, string $calendarInterval)
 * @method static mixed range(string $field)
 */
final class ElasticAggregation
{
    /**
     * @param  array<int, mixed>  $arguments
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        if (! method_exists(Agg::class, $name)) {
            throw new BadMethodCallException(sprintf('Call to undefined method %s::%s()', self::class, $name));
        }

        return Agg::$name(...$arguments);
    }
}
