<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Exceptions;

use Jackardios\QueryWizard\Exceptions\InvalidQuery;
use Symfony\Component\HttpFoundation\Response;

final class InvalidGeoShapeValue extends InvalidQuery
{
    public static function unknownType(string $propertyName, ?string $type): self
    {
        $typeStr = $type ?? 'null';
        return new static(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            "`$propertyName` has unknown shape type `$typeStr`. Supported: envelope, polygon, point, indexed_shape"
        );
    }

    public static function invalidEnvelope(string $propertyName): self
    {
        return new static(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            "`$propertyName` envelope requires coordinates as [[minLon, maxLat], [maxLon, minLat]]"
        );
    }

    public static function invalidPolygon(string $propertyName): self
    {
        return new static(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            "`$propertyName` polygon requires coordinates as array of [lon, lat] pairs"
        );
    }

    public static function invalidPoint(string $propertyName): self
    {
        return new static(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            "`$propertyName` point requires coordinates as [lon, lat]"
        );
    }

    public static function invalidIndexedShape(string $propertyName): self
    {
        return new static(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            "`$propertyName` indexed_shape requires `index` and `id` as strings"
        );
    }
}
