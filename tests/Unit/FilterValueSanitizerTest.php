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
    public function geo_bounding_box_value_swaps_inverted_coordinates(): void
    {
        $result = FilterValueSanitizer::geoBoundingBoxValue([38.0, 56.0, 36.0, 55.0], 'location');
        $this->assertEquals([36.0, 55.0, 38.0, 56.0], $result);
    }

    /** @test */
    public function geo_bounding_box_value_expands_identical_coordinates(): void
    {
        $result = FilterValueSanitizer::geoBoundingBoxValue([37.0, 55.0, 37.0, 55.0], 'location');

        $this->assertLessThan(37.0, $result[0]);
        $this->assertLessThan(55.0, $result[1]);
        $this->assertGreaterThan(37.0, $result[2]);
        $this->assertGreaterThan(55.0, $result[3]);
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
    public function geo_bounding_box_value_uses_custom_epsilon(): void
    {
        $customEpsilon = 0.1;
        $result = FilterValueSanitizer::geoBoundingBoxValue([37.0, 55.0, 37.0, 55.0], 'location', $customEpsilon);

        $this->assertEqualsWithDelta(36.9, $result[0], 0.001);
        $this->assertEqualsWithDelta(54.9, $result[1], 0.001);
        $this->assertEqualsWithDelta(37.1, $result[2], 0.001);
        $this->assertEqualsWithDelta(55.1, $result[3], 0.001);
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
}
