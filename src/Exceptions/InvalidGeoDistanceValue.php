<?php

namespace Jackardios\ElasticQueryWizard\Exceptions;

use Jackardios\QueryWizard\Exceptions\InvalidQuery;
use Symfony\Component\HttpFoundation\Response;

class InvalidGeoDistanceValue extends InvalidQuery
{
    public static function make(string $propertyName): InvalidGeoDistanceValue
    {
        return new static(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            "`$propertyName` must be valid array with `lat`, `lon` and `distance` keys, for example: `['lat' => 55.105673, 'lon' => 36.461995, 'distance' => 3000]`"
        );
    }
}
