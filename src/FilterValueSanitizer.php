<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard;

use Countable;
use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoBoundingBoxValue;
use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoDistanceValue;
use Jackardios\ElasticQueryWizard\Exceptions\InvalidRangeValue;
use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use Jackardios\QueryWizard\Support\FilterValueParser;
use Jackardios\QueryWizard\Support\ParsedDate;

/**
 * Reads the values of the built-in filters. Custom filters read theirs with
 * laravel-query-wizard's `FilterValueParser`.
 *
 * @internal
 */
class FilterValueSanitizer
{
    public const RANGE_OPERATORS = ['gt', 'gte', 'lt', 'lte'];

    public const LEGACY_RANGE_OPERATORS = ['from', 'to', 'include_lower', 'include_upper'];

    /**
     * @param  mixed  $value  raw filter value
     * @param  string|FilterInterface  $filter  the filter or its public name, for the exception
     * @return array{0: float, 1: float, 2: float, 3: float}
     *
     * @throws InvalidGeoBoundingBoxValue
     */
    public static function geoBoundingBoxValue(mixed $value, string|FilterInterface $filter): array
    {
        $bbox = [];
        $arrayValue = self::normalizeGeoBoundingBoxInput($value);

        foreach ($arrayValue as $item) {
            $bbox[] = self::finiteFloat($item) ?? throw InvalidGeoBoundingBoxValue::invalidBox($value, $filter);
        }

        if (count($bbox) !== 4) {
            throw InvalidGeoBoundingBoxValue::invalidBox($value, $filter);
        }

        [$left, $bottom, $right, $top] = $bbox;

        if (! self::isLongitude($left) || ! self::isLongitude($right) || ! self::isLatitude($bottom) || ! self::isLatitude($top)) {
            throw InvalidGeoBoundingBoxValue::invalidBox($value, $filter);
        }

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

    private static function isLongitude(float $value): bool
    {
        return $value >= -180.0 && $value <= 180.0;
    }

    private static function isLatitude(float $value): bool
    {
        return $value >= -90.0 && $value <= 90.0;
    }

    /**
     * @param  mixed  $value  raw filter value
     * @param  string|FilterInterface  $filter  the filter or its public name, for the exception
     * @return array{lat: float, lon: float, distance: string}
     *
     * @throws InvalidGeoDistanceValue
     */
    public static function geoDistanceValue(mixed $value, string|FilterInterface $filter): array
    {
        $point = is_array($value) ? $value : [];
        $lat = self::finiteFloat($point['lat'] ?? null);
        $lon = self::finiteFloat($point['lon'] ?? null);
        $rawDistance = $point['distance'] ?? null;
        $distance = (is_string($rawDistance) || is_int($rawDistance) || is_float($rawDistance)) ? trim((string) $rawDistance) : null;

        if ($lat === null || $lon === null || $distance === null
            || array_diff(array_keys($point), ['lat', 'lon', 'distance']) !== []
            || ! self::isLatitude($lat) || ! self::isLongitude($lon) || ! self::isDistance($distance)) {
            throw InvalidGeoDistanceValue::invalidDistance($value, $filter);
        }

        return ['lat' => $lat, 'lon' => $lon, 'distance' => $distance];
    }

    /**
     * A finite decimal number greater than zero with an optional Elasticsearch
     * distance unit, such as `3km` or `1.5 mi`.
     */
    private static function isDistance(string $distance): bool
    {
        $units = 'mm|millimeters|cm|centimeters|m|meters|km|kilometers|in|inch|ft|feet|yd|yards|mi|miles|NM|nmi|nauticalmiles';

        return preg_match('/^(\d+(?:\.\d*)?|\.\d+)\s*(?:'.$units.')?$/', $distance, $matches) === 1
            && (float) $matches[1] > 0.0
            && is_finite((float) $matches[1]);
    }

    /**
     * Validates and extracts range filter parameters.
     *
     * Only ES 9.x compatible operators are allowed: gt, gte, lt, lte.
     * Legacy operators (from, to, include_lower, include_upper) will throw InvalidRangeValue.
     *
     * Each bound is a decimal number or an ISO 8601 date (see laravel-query-wizard's
     * `FilterValueParser::comparable()`), or a `DateTimeInterface` from a default.
     * A date keeps its meaning, for Elasticsearch to read with the field's format
     * and the query's `time_zone`, and is written with `T` and `Z` in upper case,
     * the only ISO 8601 syntax the default `strict_date_optional_time` accepts.
     *
     * @param  mixed  $value  raw filter value
     * @param  string|FilterInterface  $filter  the filter or its public name, for the exception
     * @return array{gt?: string|int|float, gte?: string|int|float, lt?: string|int|float, lte?: string|int|float}
     *
     * @throws InvalidRangeValue
     */
    public static function rangeFilterValue(mixed $value, string|FilterInterface $filter): array
    {
        if (! is_array($value)) {
            throw InvalidRangeValue::invalidBounds($value, $filter);
        }

        $prepared = [];
        foreach ($value as $itemKey => $itemValue) {
            if (in_array($itemKey, self::LEGACY_RANGE_OPERATORS, true)) {
                throw InvalidRangeValue::legacyOperator($value, $filter, $itemKey);
            }

            if (! in_array($itemKey, self::RANGE_OPERATORS, true)) {
                throw InvalidRangeValue::invalidBounds($value, $filter);
            }

            if ($itemValue instanceof \DateTimeInterface) {
                $prepared[$itemKey] = $itemValue->format(DATE_ATOM);

                continue;
            }

            try {
                $bound = FilterValueParser::comparable($itemValue, $filter, FilterValueParser::defaultTimezone(), $itemKey);
            } catch (InvalidFilterValue $exception) {
                throw InvalidRangeValue::make($value, $filter, $exception->reason);
            }

            if ($bound === null) {
                continue;
            }

            if ($bound instanceof ParsedDate) {
                /** @var string $itemValue */
                $date = trim($itemValue);
                $bound = preg_replace(['/^(\d{4}-\d{2}-\d{2})[ t]/', '/z\z/'], ['$1T', 'Z'], $date) ?? $date;
            }

            $prepared[$itemKey] = $bound;
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
