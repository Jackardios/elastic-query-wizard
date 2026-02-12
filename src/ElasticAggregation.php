<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard;

use BadMethodCallException;
use Jackardios\EsScoutDriver\Aggregations\Agg;

/**
 * Proxy for es-scout-driver aggregation factory inside elastic-query-wizard namespace.
 *
 * ES 9.x compatibility note:
 * - histogram() aggregation on boolean fields is removed in ES 9.x.
 *   Use terms() aggregation for boolean fields instead.
 *
 * @method static mixed terms(string $field)
 * @method static mixed avg(string $field)
 * @method static mixed sum(string $field)
 * @method static mixed min(string $field)
 * @method static mixed max(string $field)
 * @method static mixed stats(string $field)
 * @method static mixed cardinality(string $field)
 * @method static mixed histogram(string $field, int|float $interval) Do not use on boolean fields (removed in ES 9.x)
 * @method static mixed dateHistogram(string $field, string $calendarInterval)
 * @method static mixed range(string $field)
 */
final class ElasticAggregation
{
    /**
     * @param array<int, mixed> $arguments
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        if (!method_exists(Agg::class, $name)) {
            throw new BadMethodCallException(sprintf('Method "%s::%s" does not exist.', self::class, $name));
        }

        return Agg::$name(...$arguments);
    }
}
