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
        $expected = 'envelope, polygon, point or indexed_shape (when the filter enables indexed shapes)';

        return self::make(
            $value,
            $filter,
            $type === null ? "Expected a shape `type`: {$expected}." : "Unsupported shape type `{$type}`. Expected {$expected}."
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
        return self::make($value, $filter, 'An indexed shape expects only an `id`.');
    }
}
