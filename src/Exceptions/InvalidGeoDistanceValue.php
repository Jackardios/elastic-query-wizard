<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Exceptions;

use Jackardios\QueryWizard\Exceptions\InvalidQuery;
use Symfony\Component\HttpFoundation\Response;

final class InvalidGeoDistanceValue extends InvalidQuery
{
    public static function make(string $propertyName): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            "`$propertyName` must be valid array with `lat`, `lon` and `distance` keys, for example: `['lat' => 55.105673, 'lon' => 36.461995, 'distance' => 3000]`"
        );
    }
}
