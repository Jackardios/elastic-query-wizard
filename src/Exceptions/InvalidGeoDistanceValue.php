<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Exceptions;

use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;

/**
 * The 400 a geo distance filter throws for a value it cannot read.
 */
final class InvalidGeoDistanceValue extends InvalidFilterValue
{
    public static function invalidDistance(mixed $value, string|FilterInterface $filter): self
    {
        return self::make(
            $value,
            $filter,
            'Expected `lat`, `lon` and `distance` keys, for example `lat=55.75&lon=37.61&distance=3km`.'
        );
    }
}
