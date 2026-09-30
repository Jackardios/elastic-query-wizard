<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard;

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
        $arrayValue = self::normalizeGeoBoundingBoxInput($value);

        if (count($arrayValue) !== 4) {
            throw InvalidGeoBoundingBoxValue::invalidBox($value, $filter);
        }

        [$left, $bottom, $right, $top] = [
            self::longitude($arrayValue[0]),
            self::latitude($arrayValue[1]),
            self::longitude($arrayValue[2]),
            self::latitude($arrayValue[3]),
        ];

        if ($left === null || $bottom === null || $right === null || $top === null) {
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

    /**
     * A longitude: a decimal number (see laravel-query-wizard's
     * `FilterValueParser::number()`) from -180 to 180, or null.
     */
    public static function longitude(mixed $value): ?float
    {
        $number = self::decimal($value);

        return $number !== null && $number >= -180.0 && $number <= 180.0 ? $number : null;
    }

    /**
     * A latitude: a decimal number from -90 to 90, or null.
     */
    public static function latitude(mixed $value): ?float
    {
        $number = self::decimal($value);

        return $number !== null && $number >= -90.0 && $number <= 90.0 ? $number : null;
    }

    private static function decimal(mixed $value): ?float
    {
        try {
            $number = FilterValueParser::number($value, '');
        } catch (InvalidFilterValue) {
            return null;
        }

        return $number === null ? null : (float) $number;
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
        $lat = self::latitude($point['lat'] ?? null);
        $lon = self::longitude($point['lon'] ?? null);
        $rawDistance = $point['distance'] ?? null;
        $distance = (is_string($rawDistance) || is_int($rawDistance) || is_float($rawDistance)) ? trim((string) $rawDistance) : null;

        if ($lat === null || $lon === null || $distance === null
            || array_diff(array_keys($point), ['lat', 'lon', 'distance']) !== []
            || ! self::isDistance($distance)) {
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
     * `FilterValueParser::comparable()`), or a `DateTimeInterface` from a default;
     * with $numbersOnly, only a decimal number.
     * A date keeps its meaning, for Elasticsearch to read with the field's format
     * and the query's `time_zone`, and is written with `T` and `Z` in upper case,
     * the only ISO 8601 syntax the default `strict_date_optional_time` accepts.
     *
     * @param  mixed  $value  raw filter value
     * @param  string|FilterInterface  $filter  the filter or its public name, for the exception
     * @param  bool  $numbersOnly  whether a date is refused
     * @return array{gt?: string|int|float, gte?: string|int|float, lt?: string|int|float, lte?: string|int|float}
     *
     * @throws InvalidRangeValue
     */
    public static function rangeFilterValue(mixed $value, string|FilterInterface $filter, bool $numbersOnly = false): array
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

            if ($itemValue instanceof \DateTimeInterface && $numbersOnly) {
                throw InvalidRangeValue::make($value, $filter, "Expected a decimal number for `{$itemKey}`.");
            }

            if ($itemValue instanceof \DateTimeInterface) {
                $prepared[$itemKey] = $itemValue->format(DATE_ATOM);

                continue;
            }

            try {
                $bound = $numbersOnly
                    ? FilterValueParser::number($itemValue, $filter, $itemKey)
                    : FilterValueParser::comparable($itemValue, $filter, FilterValueParser::defaultTimezone(), $itemKey);
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
            return array_values(array_filter($value, static fn (mixed $item): bool => ! FilterValueParser::isBlank($item)));
        }

        return FilterValueParser::isBlank($value) ? [] : [$value];
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
     * The value of a text filter, or null when blank.
     *
     * @throws InvalidFilterValue For a value that is neither a text nor a number, such as a boolean a JSON body sends
     */
    public static function text(mixed $value, FilterInterface $filter): ?string
    {
        if (FilterValueParser::isBlank($value)) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        throw InvalidFilterValue::make($value, $filter, 'Expected text.');
    }
}
