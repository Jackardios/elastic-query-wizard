<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit;

use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoBoundingBoxValue;
use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoDistanceValue;
use Jackardios\ElasticQueryWizard\Exceptions\InvalidRangeValue;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 */
class FilterValueSanitizerTest extends TestCase
{
    /** @test */
    public function to_array_returns_empty_array_for_blank_values(): void
    {
        $this->assertEquals([], FilterValueSanitizer::toArray(null));
        $this->assertEquals([], FilterValueSanitizer::toArray(''));
        $this->assertEquals([], FilterValueSanitizer::toArray('   '));
        $this->assertEquals([], FilterValueSanitizer::toArray([]));
    }

    /** @test */
    public function to_array_returns_single_element_array_for_scalar(): void
    {
        $this->assertEquals(['value'], FilterValueSanitizer::toArray('value'));
        $this->assertEquals([123], FilterValueSanitizer::toArray(123));
        $this->assertEquals([0], FilterValueSanitizer::toArray(0));
        $this->assertEquals([false], FilterValueSanitizer::toArray(false));
    }

    /** @test */
    public function to_array_splits_comma_separated_string(): void
    {
        $this->assertEquals(['a', 'b', 'c'], FilterValueSanitizer::toArray('a,b,c'));
        $this->assertEquals(['foo', 'bar'], FilterValueSanitizer::toArray('foo,bar'));
    }

    /** @test */
    public function to_array_filters_out_blank_items_from_comma_separated(): void
    {
        $this->assertEquals(['a', 'c'], FilterValueSanitizer::toArray('a,,c'));
        $this->assertEquals(['foo'], FilterValueSanitizer::toArray('foo,  ,'));
    }

    /** @test */
    public function to_array_passes_through_arrays(): void
    {
        $this->assertEquals(['a', 'b'], FilterValueSanitizer::toArray(['a', 'b']));
    }

    /** @test */
    public function to_array_filters_blank_items_from_arrays(): void
    {
        $this->assertEquals(['a', 'c'], FilterValueSanitizer::toArray(['a', '', null, 'c']));
    }

    /** @test */
    public function is_blank_returns_true_for_blank_values(): void
    {
        $this->assertTrue(FilterValueSanitizer::isBlank(null));
        $this->assertTrue(FilterValueSanitizer::isBlank(''));
        $this->assertTrue(FilterValueSanitizer::isBlank('   '));
        $this->assertTrue(FilterValueSanitizer::isBlank([]));
    }

    /** @test */
    public function is_blank_returns_false_for_filled_values(): void
    {
        $this->assertFalse(FilterValueSanitizer::isBlank('value'));
        $this->assertFalse(FilterValueSanitizer::isBlank(0));
        $this->assertFalse(FilterValueSanitizer::isBlank('0'));
        $this->assertFalse(FilterValueSanitizer::isBlank(false));
        $this->assertFalse(FilterValueSanitizer::isBlank(['a']));
    }

    /** @test */
    public function geo_bounding_box_value_returns_correct_coordinates(): void
    {
        $result = FilterValueSanitizer::geoBoundingBoxValue([36.0, 55.0, 38.0, 56.0], 'location');
        $this->assertEquals([36.0, 55.0, 38.0, 56.0], $result);
    }

    /** @test */
    public function geo_bounding_box_value_normalizes_inverted_latitudes(): void
    {
        $result = FilterValueSanitizer::geoBoundingBoxValue([36.0, 56.0, 38.0, 55.0], 'location');
        $this->assertEquals([36.0, 55.0, 38.0, 56.0], $result);
    }

    /** @test */
    public function geo_bounding_box_value_preserves_longitude_order_for_antimeridian(): void
    {
        $result = FilterValueSanitizer::geoBoundingBoxValue([170.0, -10.0, -170.0, 10.0], 'location');
        $this->assertEquals([170.0, -10.0, -170.0, 10.0], $result);
    }

    /** @test */
    public function geo_bounding_box_value_throws_for_invalid_input(): void
    {
        $this->expectException(InvalidGeoBoundingBoxValue::class);
        FilterValueSanitizer::geoBoundingBoxValue([1, 2, 3], 'location');
    }

    /** @test */
    public function geo_bounding_box_value_throws_for_non_numeric(): void
    {
        $this->expectException(InvalidGeoBoundingBoxValue::class);
        FilterValueSanitizer::geoBoundingBoxValue([1, 2, 'abc', 4], 'location');
    }

    /** @test */
    public function geo_bounding_box_value_throws_for_longitude_out_of_range(): void
    {
        $this->expectException(InvalidGeoBoundingBoxValue::class);
        FilterValueSanitizer::geoBoundingBoxValue([181.0, 55.0, 37.0, 56.0], 'location');
    }

    /** @test */
    public function geo_bounding_box_value_throws_for_latitude_out_of_range(): void
    {
        $this->expectException(InvalidGeoBoundingBoxValue::class);
        FilterValueSanitizer::geoBoundingBoxValue([37.0, -91.0, 38.0, 56.0], 'location');
    }

    /** @test */
    public function geo_bounding_box_value_accepts_comma_separated_string(): void
    {
        $result = FilterValueSanitizer::geoBoundingBoxValue('170.0,-10.0,-170.0,10.0', 'location');
        $this->assertEquals([170.0, -10.0, -170.0, 10.0], $result);
    }

    /** @test */
    public function geo_distance_value_returns_correct_structure(): void
    {
        $result = FilterValueSanitizer::geoDistanceValue(
            ['lat' => 55.75, 'lon' => 37.62, 'distance' => '10km'],
            'location'
        );

        $this->assertEquals(['lat' => 55.75, 'lon' => 37.62, 'distance' => '10km'], $result);
    }

    /** @test */
    public function geo_distance_value_throws_for_missing_lat(): void
    {
        $this->expectException(InvalidGeoDistanceValue::class);
        FilterValueSanitizer::geoDistanceValue(['lon' => 37.62, 'distance' => '10km'], 'location');
    }

    /** @test */
    public function geo_distance_value_throws_for_missing_lon(): void
    {
        $this->expectException(InvalidGeoDistanceValue::class);
        FilterValueSanitizer::geoDistanceValue(['lat' => 55.75, 'distance' => '10km'], 'location');
    }

    /** @test */
    public function geo_distance_value_throws_for_missing_distance(): void
    {
        $this->expectException(InvalidGeoDistanceValue::class);
        FilterValueSanitizer::geoDistanceValue(['lat' => 55.75, 'lon' => 37.62], 'location');
    }

    /** @test */
    public function range_filter_value_returns_valid_operators(): void
    {
        $result = FilterValueSanitizer::rangeFilterValue(
            ['gte' => 10, 'lte' => 100],
            'price'
        );

        $this->assertEquals(['gte' => 10, 'lte' => 100], $result);
    }

    /** @test */
    public function range_filter_value_filters_out_blank_values(): void
    {
        $result = FilterValueSanitizer::rangeFilterValue(
            ['gte' => 10, 'lte' => ''],
            'price'
        );

        $this->assertEquals(['gte' => 10], $result);
    }

    /** @test */
    public function range_filter_value_throws_for_invalid_operator(): void
    {
        $this->expectException(InvalidRangeValue::class);
        FilterValueSanitizer::rangeFilterValue(['min' => 10], 'price');
    }

    /** @test */
    public function range_filter_value_throws_for_non_array(): void
    {
        $this->expectException(InvalidRangeValue::class);
        FilterValueSanitizer::rangeFilterValue('invalid', 'price');
    }

    /** @test */
    public function array_with_only_filled_items_filters_correctly(): void
    {
        $result = FilterValueSanitizer::arrayWithOnlyFilledItems(['a', '', null, 'b', '  ']);
        $this->assertEquals(['a', 'b'], array_values($result));
    }

    /** @test */
    public function array_to_comma_separated_string_works_correctly(): void
    {
        $result = FilterValueSanitizer::arrayToCommaSeparatedString(['a', '', 'b', null, 'c']);
        $this->assertEquals('a,b,c', $result);
    }

    /** @test */
    public function to_string_returns_string_for_string_input(): void
    {
        $this->assertEquals('hello', FilterValueSanitizer::toString('hello'));
        $this->assertEquals('test value', FilterValueSanitizer::toString('test value'));
    }

    /** @test */
    public function to_string_returns_null_for_blank_strings(): void
    {
        $this->assertNull(FilterValueSanitizer::toString(''));
        $this->assertNull(FilterValueSanitizer::toString('   '));
    }

    /** @test */
    public function to_string_converts_numeric_to_string(): void
    {
        $this->assertEquals('123', FilterValueSanitizer::toString(123));
        $this->assertEquals('45.67', FilterValueSanitizer::toString(45.67));
        $this->assertEquals('0', FilterValueSanitizer::toString(0));
    }

    /** @test */
    public function to_string_extracts_first_element_from_array(): void
    {
        $this->assertEquals('first', FilterValueSanitizer::toString(['first', 'second']));
        $this->assertEquals('only', FilterValueSanitizer::toString(['only']));
        $this->assertEquals('123', FilterValueSanitizer::toString([123]));
    }

    /** @test */
    public function to_string_returns_null_for_empty_array(): void
    {
        $this->assertNull(FilterValueSanitizer::toString([]));
    }

    /** @test */
    public function to_string_returns_null_for_null_and_bool(): void
    {
        $this->assertNull(FilterValueSanitizer::toString(null));
        $this->assertNull(FilterValueSanitizer::toString(true));
        $this->assertNull(FilterValueSanitizer::toString(false));
    }

    /** @test */
    public function to_scalar_array_filters_non_scalar_values(): void
    {
        $result = FilterValueSanitizer::toScalarArray([1, 'a', null, 2.5, true, ['nested']]);
        $this->assertEquals([1, 'a', 2.5, true], $result);
    }

    /** @test */
    public function to_scalar_array_handles_comma_separated_string(): void
    {
        $result = FilterValueSanitizer::toScalarArray('a,b,c');
        $this->assertEquals(['a', 'b', 'c'], $result);
    }

    /** @test */
    public function to_scalar_array_returns_empty_for_blank(): void
    {
        $this->assertEquals([], FilterValueSanitizer::toScalarArray(null));
        $this->assertEquals([], FilterValueSanitizer::toScalarArray(''));
        $this->assertEquals([], FilterValueSanitizer::toScalarArray([]));
    }

    /** @test */
    public function to_scalar_array_keeps_single_scalar(): void
    {
        $this->assertEquals(['value'], FilterValueSanitizer::toScalarArray('value'));
        $this->assertEquals([123], FilterValueSanitizer::toScalarArray(123));
        $this->assertEquals([false], FilterValueSanitizer::toScalarArray(false));
    }

    /** @test */
    public function to_coordinates_array_converts_valid_coordinates(): void
    {
        $result = FilterValueSanitizer::toCoordinatesArray([[1, 2], [3, 4]]);
        $this->assertEquals([[1.0, 2.0], [3.0, 4.0]], $result);
    }

    /** @test */
    public function to_coordinates_array_converts_numeric_strings(): void
    {
        $result = FilterValueSanitizer::toCoordinatesArray([['1.5', '2.5'], ['3', '4']]);
        $this->assertEquals([[1.5, 2.5], [3.0, 4.0]], $result);
    }

    /** @test */
    public function to_coordinates_array_returns_null_for_non_array_points(): void
    {
        $this->assertNull(FilterValueSanitizer::toCoordinatesArray(['not_array', [1, 2]]));
        $this->assertNull(FilterValueSanitizer::toCoordinatesArray([123]));
    }

    /** @test */
    public function to_coordinates_array_returns_null_for_non_numeric_coords(): void
    {
        $this->assertNull(FilterValueSanitizer::toCoordinatesArray([[1, 'abc']]));
        $this->assertNull(FilterValueSanitizer::toCoordinatesArray([['x', 'y']]));
    }

    /** @test */
    public function to_coordinates_array_handles_empty_array(): void
    {
        $this->assertEquals([], FilterValueSanitizer::toCoordinatesArray([]));
    }
}
