<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Exceptions;

use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;

/**
 * The 400 a geo shape filter throws for a value it cannot read.
 */
final class InvalidGeoShapeValue extends InvalidFilterValue
{
    public static function unknownType(mixed $value, string|FilterInterface $filter, ?string $type): self
    {
        $typeString = $type ?? 'null';

        return self::make(
            $value,
            $filter,
            "Unknown shape type `{$typeString}`. Supported: envelope, polygon, point, indexed_shape."
        );
    }

    public static function invalidEnvelope(mixed $value, string|FilterInterface $filter): self
    {
        return self::make($value, $filter, 'An envelope expects coordinates as [[minLon, maxLat], [maxLon, minLat]].');
    }

    public static function invalidPolygon(mixed $value, string|FilterInterface $filter): self
    {
        return self::make(
            $value,
            $filter,
            'A polygon expects coordinates as a list of rings of at least four [lon, lat] points.'
        );
    }

    public static function invalidPoint(mixed $value, string|FilterInterface $filter): self
    {
        return self::make($value, $filter, 'A point expects coordinates as [lon, lat].');
    }

    public static function invalidIndexedShape(mixed $value, string|FilterInterface $filter): self
    {
        return self::make($value, $filter, 'An indexed shape expects an `id` string.');
    }
}
