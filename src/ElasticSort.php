<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard;

use Jackardios\ElasticQueryWizard\Sorts\FieldSort;
use Jackardios\ElasticQueryWizard\Sorts\GeoDistanceSort;
use Jackardios\ElasticQueryWizard\Sorts\NestedSort;
use Jackardios\ElasticQueryWizard\Sorts\RandomSort;
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

    /**
     * Sort by a field within nested documents.
     *
     * @param string $path The nested document path (e.g., 'variants', 'offers')
     * @param string $nestedField The field within the nested document
     * @param string $property The sort property name
     */
    public static function nested(
        string $path,
        string $nestedField,
        string $property,
        ?string $alias = null
    ): NestedSort {
        return NestedSort::make($path, $nestedField, $property, $alias);
    }

    /**
     * Random/shuffle sorting.
     *
     * @param string $property The sort property name (default: '_random')
     */
    public static function random(string $property = '_random', ?string $alias = null): RandomSort
    {
        return RandomSort::make($property, $alias);
    }
}
