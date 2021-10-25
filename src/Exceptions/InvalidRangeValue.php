<?php

namespace Jackardios\ElasticQueryWizard\Exceptions;

use Jackardios\QueryWizard\Exceptions\InvalidQuery;
use Symfony\Component\HttpFoundation\Response;

class InvalidRangeValue extends InvalidQuery
{
    public static function make(string $propertyName): InvalidRangeValue
    {
        return new static(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            "`$propertyName` must be valid array with valid keys: `gt`, `gte`, `lt` or `lte`"
        );
    }
}
