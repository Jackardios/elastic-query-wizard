<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard;

use Jackardios\ElasticQueryWizard\Sorts\FieldSort;
use Jackardios\ElasticQueryWizard\Sorts\GeoDistanceSort;
use Jackardios\ElasticQueryWizard\Sorts\ScoreSort;
use Jackardios\ElasticQueryWizard\Sorts\ScriptSort;
use Jackardios\QueryWizard\Sorts\CallbackSort;

final class ElasticSort
{
    public static function field(string $property, ?string $alias = null): FieldSort
    {
        return FieldSort::make($property, $alias);
    }

    public static function callback(string $name, callable $callback, ?string $alias = null): CallbackSort
    {
        return CallbackSort::make($name, $callback, $alias);
    }

    public static function geoDistance(
        string $property,
        float $lat,
        float $lon,
        ?string $alias = null
    ): GeoDistanceSort {
        return GeoDistanceSort::make($property, $lat, $lon, $alias);
    }

    public static function script(
        string $scriptSource,
        string $property,
        ?string $alias = null
    ): ScriptSort {
        return ScriptSort::make($scriptSource, $property, $alias);
    }

    public static function score(?string $alias = null): ScoreSort
    {
        return ScoreSort::make($alias);
    }
}
