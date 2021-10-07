<?php

namespace Jackardios\ElasticQueryWizard\Exceptions;

use Jackardios\QueryWizard\Exceptions\InvalidQuery;
use Symfony\Component\HttpFoundation\Response;

class InvalidGeoBoundingBoxValue extends InvalidQuery
{
    public static function make(string $propertyName): InvalidGeoBoundingBoxValue
    {
        return new static(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            "`$propertyName` must be valid geo bounding box array in `left,bottom,right,top` format"
        );
    }
}
