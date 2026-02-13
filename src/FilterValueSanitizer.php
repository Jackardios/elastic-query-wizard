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
     * @param mixed $value raw filter value
     * @param string $propertyName will be used to throw exception
     * @return array{0: float, 1: float, 2: float, 3: float}
     * @throws InvalidGeoBoundingBoxValue
     */
    public static function geoBoundingBoxValue(mixed $value, string $propertyName): array
    {
        $bbox = [];
        $arrayValue = self::normalizeGeoBoundingBoxInput($value);

        foreach ($arrayValue as $item) {
            if (! is_numeric($item)) {
                throw InvalidGeoBoundingBoxValue::make($propertyName);
            }

            $bbox[] = floatval($item);
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
     * @return array<int, mixed>
     */
    private static function normalizeGeoBoundingBoxInput(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
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
     * Validates and extracts range filter parameters.
     *
     * Only ES 9.x compatible operators are allowed: gt, gte, lt, lte.
     * Legacy operators (from, to, include_lower, include_upper) will throw InvalidRangeValue.
     *
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
            if (in_array($itemKey, self::LEGACY_RANGE_OPERATORS, true)) {
                throw InvalidRangeValue::legacyOperator($propertyName, $itemKey);
            }

            if (! in_array($itemKey, self::RANGE_OPERATORS, true) || ! (is_string($itemValue) || is_numeric($itemValue))) {
                throw InvalidRangeValue::make($propertyName);
            }

            if (static::isFilled($itemValue)) {
                $prepared[$itemKey] = $itemValue;
            }
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
