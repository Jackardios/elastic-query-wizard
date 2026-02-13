<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoDistanceValue;
use Jackardios\ElasticQueryWizard\Filters\GeoDistanceFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\GeoModel;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;

/**
 * @group unit
 * @group filter
 */
class GeoDistanceFilterQueryTest extends UnitTestCase
{
    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
    public function it_does_not_add_a_query_for_empty_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['location' => []])
            ->allowedFilters(GeoDistanceFilter::make('location'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    /** @test */
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

    /** @test */
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
}
