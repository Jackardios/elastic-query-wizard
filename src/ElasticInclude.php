<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard;

use Jackardios\QueryWizard\Eloquent\Includes\CountInclude;
use Jackardios\QueryWizard\Eloquent\Includes\RelationshipInclude;
use Jackardios\QueryWizard\Includes\CallbackInclude;

final class ElasticInclude
{
    public static function relationship(string $relation, ?string $alias = null): RelationshipInclude
    {
        return RelationshipInclude::make($relation, $alias);
    }

    public static function count(string $relation, ?string $alias = null): CountInclude
    {
        return CountInclude::make($relation, $alias);
    }

    public static function callback(string $name, callable $callback, ?string $alias = null): CallbackInclude
    {
        return CallbackInclude::make($name, $callback, $alias);
    }
}
