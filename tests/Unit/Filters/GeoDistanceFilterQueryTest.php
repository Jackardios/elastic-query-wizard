<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoDistanceValue;
use Jackardios\ElasticQueryWizard\Filters\GeoDistanceFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\GeoModel;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
#[Group('filter')]
class GeoDistanceFilterQueryTest extends UnitTestCase
{
    #[Test]
    public function it_builds_a_geo_distance_query(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'location' => ['lat' => '55.75', 'lon' => '37.62', 'distance' => '10km'],
            ], GeoModel::class)
            ->allowedFilters(GeoDistanceFilter::make('location'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertArrayHasKey('geo_distance', $queries[0]);
        $geoDistance = $queries[0]['geo_distance'];
        $this->assertEquals(['lat' => 55.75, 'lon' => 37.62], $geoDistance['location']);
        $this->assertEquals('10km', $geoDistance['distance']);
    }

    #[Test]
    public function it_throws_for_missing_lat(): void
    {
        $this->expectException(InvalidGeoDistanceValue::class);

        $this
            ->createElasticWizardWithFilters([
                'location' => ['lon' => '37.62', 'distance' => '10km'],
            ], GeoModel::class)
            ->allowedFilters(GeoDistanceFilter::make('location'))
            ->build();
    }

    #[Test]
    public function it_throws_for_missing_distance(): void
    {
        $this->expectException(InvalidGeoDistanceValue::class);

        $this
            ->createElasticWizardWithFilters([
                'location' => ['lat' => '55.75', 'lon' => '37.62'],
            ], GeoModel::class)
            ->allowedFilters(GeoDistanceFilter::make('location'))
            ->build();
    }

    #[Test]
    public function it_does_not_add_a_query_for_empty_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['location' => []])
            ->allowedFilters(GeoDistanceFilter::make('location'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    #[Test]
    public function it_throws_for_non_numeric_lat(): void
    {
        $this->expectException(InvalidGeoDistanceValue::class);

        $this
            ->createElasticWizardWithFilters([
                'location' => ['lat' => 'abc', 'lon' => '37.62', 'distance' => '10km'],
            ], GeoModel::class)
            ->allowedFilters(GeoDistanceFilter::make('location'))
            ->build();
    }

    #[Test]
    public function it_throws_for_zero_string_scalar_value(): void
    {
        $this->expectException(InvalidGeoDistanceValue::class);

        $this
            ->createElasticWizardWithFilters([
                'location' => '0',
            ], GeoModel::class)
            ->allowedFilters(GeoDistanceFilter::make('location'))
            ->build();
    }

    #[Test]
    public function an_infinite_coordinate_is_refused(): void
    {
        $this->expectException(InvalidGeoDistanceValue::class);

        $this
            ->createElasticWizardWithFilters([
                'location' => ['lat' => '1e999', 'lon' => '37.62', 'distance' => '10km'],
            ], GeoModel::class)
            ->allowedFilters(GeoDistanceFilter::make('location'))
            ->build();
    }

    #[Test]
    public function a_distance_too_long_to_be_finite_is_refused(): void
    {
        $this->expectException(InvalidGeoDistanceValue::class);

        $this
            ->createElasticWizardWithFilters([
                'location' => ['lat' => '55.75', 'lon' => '37.62', 'distance' => str_repeat('9', 400).'km'],
            ], GeoModel::class)
            ->allowedFilters(GeoDistanceFilter::make('location'))
            ->build();
    }

    #[Test]
    public function a_key_other_than_lat_lon_and_distance_is_refused(): void
    {
        $this->expectException(InvalidGeoDistanceValue::class);

        $this
            ->createElasticWizardWithFilters([
                'location' => ['lat' => '55.75', 'lon' => '37.62', 'distance' => '10km', 'unit' => 'mi'],
            ], GeoModel::class)
            ->allowedFilters(GeoDistanceFilter::make('location'))
            ->build();
    }

    #[Test]
    public function a_prepared_value_of_blank_parts_applies_no_condition(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['location' => 'here'], GeoModel::class)
            ->allowedFilters(GeoDistanceFilter::make('location')->prepareValueWith(fn (): array => ['lat' => '', 'lon' => ' ', 'distance' => '']));
        $wizard->build();

        $this->assertEmpty($this->getFilterQueries($wizard->boolQuery()));
    }

    #[Test]
    public function a_coordinate_in_exponent_notation_is_refused(): void
    {
        $this->expectException(InvalidGeoDistanceValue::class);

        $this
            ->createElasticWizardWithFilters([
                'location' => ['lat' => '1e1', 'lon' => '37.62', 'distance' => '10km'],
            ], GeoModel::class)
            ->allowedFilters(GeoDistanceFilter::make('location'))
            ->build();
    }
}
