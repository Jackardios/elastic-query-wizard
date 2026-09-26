<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard;

use Countable;
use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoBoundingBoxValue;
use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoDistanceValue;
use Jackardios\ElasticQueryWizard\Exceptions\InvalidRangeValue;

class FilterValueSanitizer
{
    public const RANGE_OPERATORS = ['gt', 'gte', 'lt', 'lte'];

    public const LEGACY_RANGE_OPERATORS = ['from', 'to', 'include_lower', 'include_upper'];

    /**
     * @param  mixed  $value  raw filter value
     * @param  string  $propertyName  will be used to throw exception
     * @return array{0: float, 1: float, 2: float, 3: float}
     *
     * @throws InvalidGeoBoundingBoxValue
     */
    public static function geoBoundingBoxValue(mixed $value, string $propertyName): array
    {
        $bbox = [];
        $arrayValue = self::normalizeGeoBoundingBoxInput($value);

        foreach ($arrayValue as $item) {
            $bbox[] = self::finiteFloat($item) ?? throw InvalidGeoBoundingBoxValue::make($propertyName);
        }

        if (count($bbox) !== 4) {
            throw InvalidGeoBoundingBoxValue::make($propertyName);
        }

        [$left, $bottom, $right, $top] = $bbox;

        self::assertLongitude($left, $propertyName);
        self::assertLongitude($right, $propertyName);
        self::assertLatitude($bottom, $propertyName);
        self::assertLatitude($top, $propertyName);

        // Normalize latitude axis only. Longitude order must be preserved to support
        // antimeridian-crossing boxes where left > right is intentional.
        if ($bottom > $top) {
            [$top, $bottom] = [$bottom, $top];
        }

        return [$left, $bottom, $right, $top];
    }

    /**
     * The edges in left, bottom, right, top order: a list is taken in that
     * order, named edges by name.
     *
     * @return array<int, mixed>
     */
    private static function normalizeGeoBoundingBoxInput(mixed $value): array
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return $value;
            }

            $edges = ['left', 'bottom', 'right', 'top'];

            if (count($value) !== 4 || array_diff(array_keys($value), $edges) !== []) {
                return [];
            }

            return array_map(static fn (string $edge): mixed => $value[$edge], $edges);
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }

            return str_contains($trimmed, ',')
                ? array_map('trim', explode(',', $trimmed))
                : [$trimmed];
        }

        return [];
    }

    /**
     * @throws InvalidGeoBoundingBoxValue
     */
    private static function assertLongitude(float $value, string $propertyName): void
    {
        if ($value < -180.0 || $value > 180.0) {
            throw InvalidGeoBoundingBoxValue::make($propertyName);
        }
    }

    /**
     * @throws InvalidGeoBoundingBoxValue
     */
    private static function assertLatitude(float $value, string $propertyName): void
    {
        if ($value < -90.0 || $value > 90.0) {
            throw InvalidGeoBoundingBoxValue::make($propertyName);
        }
    }

    /**
     * @param  mixed  $value  raw filter value
     * @param  string  $propertyName  will be used to throw exception
     * @return array{lat: float, lon: float, distance: string}
     *
     * @throws InvalidGeoDistanceValue
     */
    public static function geoDistanceValue(mixed $value, string $propertyName): array
    {
        $value = is_array($value) ? $value : [];
        $lat = self::finiteFloat($value['lat'] ?? null);
        $lon = self::finiteFloat($value['lon'] ?? null);
        $rawDistance = $value['distance'] ?? null;
        $distance = (is_string($rawDistance) || is_numeric($rawDistance)) ? trim((string) $rawDistance) : null;

        if (! isset($lat, $lon, $distance)) {
            throw InvalidGeoDistanceValue::make($propertyName);
        }

        return ['lat' => $lat, 'lon' => $lon, 'distance' => $distance];
    }

    /**
     * Validates and extracts range filter parameters.
     *
     * Only ES 9.x compatible operators are allowed: gt, gte, lt, lte.
     * Legacy operators (from, to, include_lower, include_upper) will throw InvalidRangeValue.
     *
     * @param  mixed  $value  raw filter value
     * @param  string  $propertyName  will be used to throw exception
     * @return array{gt?: string|int|float, gte?: string|int|float, lt?: string|int|float, lte?: string|int|float}
     *
     * @throws InvalidRangeValue
     */
    public static function rangeFilterValue(mixed $value, string $propertyName): array
    {
        if (! is_array($value)) {
            throw InvalidRangeValue::make($propertyName);
        }

        $prepared = [];
        foreach ($value as $itemKey => $itemValue) {
            if (in_array($itemKey, self::LEGACY_RANGE_OPERATORS, true)) {
                throw InvalidRangeValue::legacyOperator($propertyName, $itemKey);
            }

            if (! in_array($itemKey, self::RANGE_OPERATORS, true)) {
                throw InvalidRangeValue::make($propertyName);
            }

            if (static::isBlank($itemValue)) {
                continue;
            }

            if (! is_string($itemValue) && ! is_numeric($itemValue)) {
                throw InvalidRangeValue::make($propertyName);
            }

            $prepared[$itemKey] = $itemValue;
        }

        return $prepared;
    }

    public static function isBlank(mixed $value): bool
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

    public static function isFilled(mixed $value): bool
    {
        return ! static::isBlank($value);
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param  array<TKey, TValue>  $array
     * @return array<TKey, TValue>
     */
    public static function arrayWithOnlyFilledItems(array $array): array
    {
        return array_filter($array, static fn ($item) => static::isFilled($item));
    }

    /**
     * @param  array<mixed>  $array
     */
    public static function arrayToCommaSeparatedString(array $array): string
    {
        $items = [];

        foreach (static::arrayWithOnlyFilledItems($array) as $item) {
            if (is_scalar($item) || $item instanceof \Stringable) {
                $items[] = (string) $item;
            }
        }

        return implode(',', $items);
    }

    /**
     * Converts a value to a list without blank items.
     *
     * A string is one item: laravel-query-wizard has already split the request
     * value by the configured separator, unless the filter keeps it whole.
     *
     * @return array<int, mixed>
     */
    public static function toArray(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(static::arrayWithOnlyFilledItems($value));
        }

        return static::isFilled($value) ? [$value] : [];
    }

    /**
     * Converts a value to a scalar array, filtering out non-scalar values.
     *
     * @return array<int, bool|float|int|string>
     */
    public static function toScalarArray(mixed $value): array
    {
        $items = self::toArray($value);

        return array_values(array_filter($items, static fn ($item) => is_scalar($item)));
    }

    /**
     * Converts a value to a string, returning null for non-stringable values.
     */
    public static function toString(mixed $value): ?string
    {
        if (is_array($value)) {
            $extracted = reset($value);
            $value = $extracted !== false ? $extracted : '';
        }

        if (is_string($value)) {
            return self::isBlank($value) ? null : $value;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return null;
    }

    /**
     * Validates and converts coordinates to float array format.
     *
     * @param  array<mixed>  $coordinates
     * @return array<int, array<int, float>>|null
     */
    public static function toCoordinatesArray(array $coordinates): ?array
    {
        $result = [];
        foreach ($coordinates as $point) {
            if (! is_array($point)) {
                return null;
            }
            $floats = [];
            foreach ($point as $coord) {
                $float = self::finiteFloat($coord);

                if ($float === null) {
                    return null;
                }

                $floats[] = $float;
            }
            $result[] = $floats;
        }

        return $result;
    }

    /**
     * A number as a float, or null when the value is not a number or overflows
     * to infinity (e.g. "1e999"), which JSON can't encode.
     */
    public static function finiteFloat(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $float = (float) $value;

        return is_finite($float) ? $float : null;
    }
}
