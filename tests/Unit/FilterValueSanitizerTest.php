<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit;

use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoBoundingBoxValue;
use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoDistanceValue;
use Jackardios\ElasticQueryWizard\Exceptions\InvalidRangeValue;
use Jackardios\ElasticQueryWizard\Filters\MatchFilter;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class FilterValueSanitizerTest extends TestCase
{
    #[Test]
    public function to_array_returns_empty_array_for_blank_values(): void
    {
        $this->assertEquals([], FilterValueSanitizer::toArray(null));
        $this->assertEquals([], FilterValueSanitizer::toArray(''));
        $this->assertEquals([], FilterValueSanitizer::toArray('   '));
        $this->assertEquals([], FilterValueSanitizer::toArray([]));
    }

    #[Test]
    public function to_array_returns_single_element_array_for_scalar(): void
    {
        $this->assertEquals(['value'], FilterValueSanitizer::toArray('value'));
        $this->assertEquals([123], FilterValueSanitizer::toArray(123));
        $this->assertEquals([0], FilterValueSanitizer::toArray(0));
        $this->assertEquals([false], FilterValueSanitizer::toArray(false));
    }

    #[Test]
    public function to_array_keeps_a_string_with_commas_whole(): void
    {
        $this->assertEquals(['a,b,c'], FilterValueSanitizer::toArray('a,b,c'));
        $this->assertEquals(['Smith, John'], FilterValueSanitizer::toArray('Smith, John'));
    }

    #[Test]
    public function to_array_filters_out_blank_items_from_a_split_value(): void
    {
        $this->assertEquals(['a', 'c'], FilterValueSanitizer::toArray(['a', '', 'c']));
        $this->assertEquals(['foo'], FilterValueSanitizer::toArray(['foo', '  ']));
    }

    #[Test]
    public function to_array_passes_through_arrays(): void
    {
        $this->assertEquals(['a', 'b'], FilterValueSanitizer::toArray(['a', 'b']));
    }

    #[Test]
    public function to_array_filters_blank_items_from_arrays(): void
    {
        $this->assertEquals(['a', 'c'], FilterValueSanitizer::toArray(['a', '', null, [' '], 'c']));
    }

    #[Test]
    public function geo_bounding_box_value_returns_correct_coordinates(): void
    {
        $result = FilterValueSanitizer::geoBoundingBoxValue([36.0, 55.0, 38.0, 56.0], 'location');
        $this->assertEquals([36.0, 55.0, 38.0, 56.0], $result);
    }

    #[Test]
    public function geo_bounding_box_value_normalizes_inverted_latitudes(): void
    {
        $result = FilterValueSanitizer::geoBoundingBoxValue([36.0, 56.0, 38.0, 55.0], 'location');
        $this->assertEquals([36.0, 55.0, 38.0, 56.0], $result);
    }

    #[Test]
    public function geo_bounding_box_value_preserves_longitude_order_for_antimeridian(): void
    {
        $result = FilterValueSanitizer::geoBoundingBoxValue([170.0, -10.0, -170.0, 10.0], 'location');
        $this->assertEquals([170.0, -10.0, -170.0, 10.0], $result);
    }

    #[Test]
    public function geo_bounding_box_value_throws_for_invalid_input(): void
    {
        $this->expectException(InvalidGeoBoundingBoxValue::class);
        FilterValueSanitizer::geoBoundingBoxValue([1, 2, 3], 'location');
    }

    #[Test]
    public function geo_bounding_box_value_throws_for_non_numeric(): void
    {
        $this->expectException(InvalidGeoBoundingBoxValue::class);
        FilterValueSanitizer::geoBoundingBoxValue([1, 2, 'abc', 4], 'location');
    }

    #[Test]
    public function geo_bounding_box_value_throws_for_longitude_out_of_range(): void
    {
        $this->expectException(InvalidGeoBoundingBoxValue::class);
        FilterValueSanitizer::geoBoundingBoxValue([181.0, 55.0, 37.0, 56.0], 'location');
    }

    #[Test]
    public function geo_bounding_box_value_throws_for_latitude_out_of_range(): void
    {
        $this->expectException(InvalidGeoBoundingBoxValue::class);
        FilterValueSanitizer::geoBoundingBoxValue([37.0, -91.0, 38.0, 56.0], 'location');
    }

    #[Test]
    public function geo_bounding_box_value_accepts_comma_separated_string(): void
    {
        $result = FilterValueSanitizer::geoBoundingBoxValue('170.0,-10.0,-170.0,10.0', 'location');
        $this->assertEquals([170.0, -10.0, -170.0, 10.0], $result);
    }

    #[Test]
    public function geo_distance_value_returns_correct_structure(): void
    {
        $result = FilterValueSanitizer::geoDistanceValue(
            ['lat' => 55.75, 'lon' => 37.62, 'distance' => '10km'],
            'location'
        );

        $this->assertEquals(['lat' => 55.75, 'lon' => 37.62, 'distance' => '10km'], $result);
    }

    #[Test]
    public function geo_distance_value_throws_for_missing_lat(): void
    {
        $this->expectException(InvalidGeoDistanceValue::class);
        FilterValueSanitizer::geoDistanceValue(['lon' => 37.62, 'distance' => '10km'], 'location');
    }

    #[Test]
    public function geo_distance_value_throws_for_missing_lon(): void
    {
        $this->expectException(InvalidGeoDistanceValue::class);
        FilterValueSanitizer::geoDistanceValue(['lat' => 55.75, 'distance' => '10km'], 'location');
    }

    #[Test]
    public function geo_distance_value_throws_for_missing_distance(): void
    {
        $this->expectException(InvalidGeoDistanceValue::class);
        FilterValueSanitizer::geoDistanceValue(['lat' => 55.75, 'lon' => 37.62], 'location');
    }

    #[Test]
    public function range_filter_value_returns_valid_operators(): void
    {
        $result = FilterValueSanitizer::rangeFilterValue(
            ['gte' => 10, 'lte' => 100],
            'price'
        );

        $this->assertEquals(['gte' => 10, 'lte' => 100], $result);
    }

    #[Test]
    public function range_filter_value_filters_out_blank_values(): void
    {
        $result = FilterValueSanitizer::rangeFilterValue(
            ['gte' => 10, 'lte' => ''],
            'price'
        );

        $this->assertEquals(['gte' => 10], $result);
    }

    #[Test]
    public function range_filter_value_throws_for_invalid_operator(): void
    {
        $this->expectException(InvalidRangeValue::class);
        FilterValueSanitizer::rangeFilterValue(['min' => 10], 'price');
    }

    #[Test]
    public function range_filter_value_throws_for_non_array(): void
    {
        $this->expectException(InvalidRangeValue::class);
        FilterValueSanitizer::rangeFilterValue('invalid', 'price');
    }

    #[Test]
    public function text_returns_a_string_as_given(): void
    {
        $this->assertSame('hello', FilterValueSanitizer::text('hello', MatchFilter::make('title')));
        $this->assertSame(' test value ', FilterValueSanitizer::text(' test value ', MatchFilter::make('title')));
    }

    #[Test]
    public function text_returns_null_for_a_blank_value(): void
    {
        foreach ([null, '', '   ', [], [''], [null, ' ']] as $value) {
            $this->assertNull(FilterValueSanitizer::text($value, MatchFilter::make('title')));
        }
    }

    #[Test]
    public function text_converts_a_number_to_a_string(): void
    {
        $this->assertSame('123', FilterValueSanitizer::text(123, MatchFilter::make('title')));
        $this->assertSame('45.67', FilterValueSanitizer::text(45.67, MatchFilter::make('title')));
        $this->assertSame('0', FilterValueSanitizer::text(0, MatchFilter::make('title')));
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function valuesThatAreNotTexts(): array
    {
        return [
            'true' => [true],
            'false' => [false],
            'list' => [['first', 'second']],
            'list of one' => [['only']],
        ];
    }

    #[Test]
    #[DataProvider('valuesThatAreNotTexts')]
    public function text_refuses_a_value_that_is_not_a_text(mixed $value): void
    {
        $this->expectException(InvalidFilterValue::class);
        $this->expectExceptionMessage('Expected text.');

        FilterValueSanitizer::text($value, MatchFilter::make('title'));
    }

    #[Test]
    public function to_scalar_array_filters_non_scalar_values(): void
    {
        $result = FilterValueSanitizer::toScalarArray([1, 'a', null, 2.5, true, ['nested']]);
        $this->assertEquals([1, 'a', 2.5, true], $result);
    }

    #[Test]
    public function to_scalar_array_keeps_a_string_with_commas_whole(): void
    {
        $this->assertEquals(['a,b,c'], FilterValueSanitizer::toScalarArray('a,b,c'));
    }

    #[Test]
    public function to_scalar_array_returns_empty_for_blank(): void
    {
        $this->assertEquals([], FilterValueSanitizer::toScalarArray(null));
        $this->assertEquals([], FilterValueSanitizer::toScalarArray(''));
        $this->assertEquals([], FilterValueSanitizer::toScalarArray([]));
    }

    #[Test]
    public function to_scalar_array_keeps_single_scalar(): void
    {
        $this->assertEquals(['value'], FilterValueSanitizer::toScalarArray('value'));
        $this->assertEquals([123], FilterValueSanitizer::toScalarArray(123));
        $this->assertEquals([false], FilterValueSanitizer::toScalarArray(false));
    }

    #[Test]
    public function finite_float_reads_numbers_and_refuses_infinity(): void
    {
        $this->assertSame(1.5, FilterValueSanitizer::finiteFloat('1.5'));
        $this->assertSame(3.0, FilterValueSanitizer::finiteFloat(3));
        $this->assertNull(FilterValueSanitizer::finiteFloat('1e999'));
        $this->assertNull(FilterValueSanitizer::finiteFloat('abc'));
        $this->assertNull(FilterValueSanitizer::finiteFloat([1]));
    }
}
