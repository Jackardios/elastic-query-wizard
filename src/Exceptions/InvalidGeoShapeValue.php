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
    private const MAX_ECHOED_TYPE_LENGTH = 50;

    public static function unknownType(mixed $value, string|FilterInterface $filter, ?string $type): self
    {
        $expected = 'envelope, polygon, point or indexed_shape (when the filter enables indexed shapes)';

        return self::make(
            $value,
            $filter,
            $type === null ? "Expected a shape `type`: {$expected}." : 'Unsupported shape type `'.self::shortenType($type)."`. Expected {$expected}."
        );
    }

    public static function unexpectedKeys(mixed $value, string|FilterInterface $filter, string $type): self
    {
        return self::make($value, $filter, "A shape of type `{$type}` expects only `type` and `coordinates`.");
    }

    private static function shortenType(string $type): string
    {
        $type = mb_scrub($type, 'UTF-8');

        return mb_strlen($type) > self::MAX_ECHOED_TYPE_LENGTH ? mb_substr($type, 0, self::MAX_ECHOED_TYPE_LENGTH).'…' : $type;
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
