<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoBoundingBoxValue;
use Jackardios\ElasticQueryWizard\Filters\GeoBoundingBoxFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\GeoModel;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;

/**
 * @group unit
 * @group filter
 */
class GeoBoundingBoxFilterQueryTest extends UnitTestCase
{
    /** @test */
    public function it_builds_a_geo_bounding_box_query(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'location' => ['36.0', '55.0', '37.0', '56.0'],
            ], GeoModel::class)
            ->allowedFilters(GeoBoundingBoxFilter::make('location'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertArrayHasKey('geo_bounding_box', $queries[0]);
        $this->assertArrayHasKey('location', $queries[0]['geo_bounding_box']);
        $this->assertArrayHasKey('top_left', $queries[0]['geo_bounding_box']['location']);
        $this->assertArrayHasKey('bottom_right', $queries[0]['geo_bounding_box']['location']);
    }

    /** @test */
    public function it_throws_for_invalid_bbox_value(): void
    {
        $this->expectException(InvalidGeoBoundingBoxValue::class);

        $this
            ->createElasticWizardWithFilters([
                'location' => ['not', 'valid'],
            ], GeoModel::class)
            ->allowedFilters(GeoBoundingBoxFilter::make('location'))
            ->build();
    }

    /** @test */
    public function it_throws_for_wrong_count_of_values(): void
    {
        $this->expectException(InvalidGeoBoundingBoxValue::class);

        $this
            ->createElasticWizardWithFilters([
                'location' => ['36.0', '55.0', '37.0'],
            ], GeoModel::class)
            ->allowedFilters(GeoBoundingBoxFilter::make('location'))
            ->build();
    }

    /** @test */
    public function it_does_not_add_a_query_for_empty_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['location' => []])
            ->allowedFilters(GeoBoundingBoxFilter::make('location'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    /** @test */
    public function it_preserves_longitude_order_for_antimeridian_crossing_boxes(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'location' => ['170.0', '-10.0', '-170.0', '10.0'],
            ], GeoModel::class)
            ->allowedFilters(GeoBoundingBoxFilter::make('location'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);

        $topLeft = $queries[0]['geo_bounding_box']['location']['top_left'];
        $bottomRight = $queries[0]['geo_bounding_box']['location']['bottom_right'];

        $this->assertSame(170.0, $topLeft['lon']);
        $this->assertSame(-170.0, $bottomRight['lon']);
        $this->assertSame(10.0, $topLeft['lat']);
        $this->assertSame(-10.0, $bottomRight['lat']);
    }
}
