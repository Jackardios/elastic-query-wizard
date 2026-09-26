<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Exceptions;

use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;

/**
 * The 400 a geo bounding box filter throws for a value it cannot read.
 */
final class InvalidGeoBoundingBoxValue extends InvalidFilterValue
{
    public static function invalidBox(mixed $value, string|FilterInterface $filter): self
    {
        return self::make(
            $value,
            $filter,
            'Expected four coordinates in `left,bottom,right,top` order, or `left`, `bottom`, `right` and `top` keys.'
        );
    }
}
