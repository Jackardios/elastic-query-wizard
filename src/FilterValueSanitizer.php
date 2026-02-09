<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard;

use Countable;
use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoBoundingBoxValue;
use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoDistanceValue;
use Jackardios\ElasticQueryWizard\Exceptions\InvalidRangeValue;

class FilterValueSanitizer
{
    /**
     * @param mixed $value raw filter value
     * @param string $propertyName will be used to throw exception
     * @param float $epsilon tolerance for comparing coordinates
     * @return array{0: float, 1: float, 2: float, 3: float}
     * @throws InvalidGeoBoundingBoxValue
     */
    public static function geoBoundingBoxValue(mixed $value, string $propertyName, float $epsilon = 0.00001): array
    {
        $bbox = [];
        $arrayValue = is_array($value) ? $value : [];

        foreach($arrayValue as $item) {
            if (! is_numeric($item)) {
                throw InvalidGeoBoundingBoxValue::make($propertyName);
            }

            $bbox[] = floatval($item);
        }

        if (count($bbox) !== 4) {
            throw InvalidGeoBoundingBoxValue::make($propertyName);
        }

        [$left, $bottom, $right, $top] = $bbox;

        if ($left > $right) {
            [$left, $right] = [$right, $left];
        } elseif (abs($left - $right) < $epsilon) {
            $left -= $epsilon;
            $right += $epsilon;
        }

        if ($bottom > $top) {
            [$top, $bottom] = [$bottom, $top];
        } elseif (abs($bottom - $top) < $epsilon) {
            $bottom -= $epsilon;
            $top += $epsilon;
        }

        return [$left, $bottom, $right, $top];
    }

    /**
     * @param mixed $value raw filter value
     * @param string $propertyName will be used to throw exception
     * @return array{lat: float, lon: float, distance: string}
     * @throws InvalidGeoDistanceValue
     */
    public static function geoDistanceValue(mixed $value, string $propertyName): array
    {
        $value = is_array($value) ? $value : [];
        $lat = is_numeric($value['lat'] ?? null) ? floatval($value['lat']) : null;
        $lon = is_numeric($value['lon'] ?? null) ? floatval($value['lon']) : null;
        $rawDistance = $value['distance'] ?? null;
        $distance = (is_string($rawDistance) || is_numeric($rawDistance)) ? trim((string) $rawDistance) : null;

        if (! isset($lat, $lon, $distance)) {
            throw InvalidGeoDistanceValue::make($propertyName);
        }

        return ['lat' => $lat, 'lon' => $lon, 'distance' => $distance];
    }

    /**
     * @param mixed $value raw filter value
     * @param string $propertyName will be used to throw exception
     * @return array{gt?: string|number, gte?: string|number, lt?: string|number, lte?: string|number}
     * @throws InvalidRangeValue
     */
    public static function rangeFilterValue(mixed $value, string $propertyName): array
    {
        if (! is_array($value)) {
            throw InvalidRangeValue::make($propertyName);
        }

        $prepared = [];
        foreach ($value as $itemKey => $itemValue) {
            if (! in_array($itemKey, ['gt', 'gte', 'lt', 'lte']) || ! (is_string($itemValue) || is_numeric($itemValue))) {
                throw InvalidRangeValue::make($propertyName);
            }

            if (static::isFilled($itemValue)) {
                $prepared[$itemKey] = $itemValue;
            }
        }

        return $prepared;
    }

    public static function isBlank($value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_numeric($value) || is_bool($value)) {
            return false;
        }

        if ($value instanceof Countable) {
            return count($value) === 0;
        }

        return empty($value);
    }

    public static function isFilled($value): bool
    {
        return ! static::isBlank($value);
    }

    public static function arrayWithOnlyFilledItems(array $array): array
    {
        return array_filter($array, static fn($item) => static::isFilled($item));
    }

    public static function arrayToCommaSeparatedString(array $array): string
    {
        return implode(',', static::arrayWithOnlyFilledItems($array));
    }

    /**
     * Converts a value to an array, handling comma-separated strings.
     *
     * @return array<int, mixed>
     */
    public static function toArray(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(static::arrayWithOnlyFilledItems($value));
        }

        if (is_string($value) && str_contains($value, ',')) {
            return array_values(static::arrayWithOnlyFilledItems(explode(',', $value)));
        }

        return static::isFilled($value) ? [$value] : [];
    }
}
