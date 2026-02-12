<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoShapeValue;
use Jackardios\ElasticQueryWizard\Filters\GeoShapeFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\GeoModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Jackardios\EloquentSpatial\Objects\Point;

/**
 * @group elastic
 * @group filter
 * @group elastic-filter
 */
class GeoShapeFilterTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = collect([
            // Moscow center area
            GeoModel::factory()->create([
                'name' => 'Moscow Center',
                'location' => new Point(37.6173, 55.7558),
                'boundary' => [
                    'type' => 'envelope',
                    'coordinates' => [[37.5, 55.8], [37.7, 55.7]],
                ],
            ]),
            // Moscow North area
            GeoModel::factory()->create([
                'name' => 'Moscow North',
                'location' => new Point(37.6173, 55.9),
                'boundary' => [
                    'type' => 'envelope',
                    'coordinates' => [[37.5, 56.0], [37.7, 55.85]],
                ],
            ]),
            // Saint Petersburg area
            GeoModel::factory()->create([
                'name' => 'Saint Petersburg',
                'location' => new Point(30.3351, 59.9343),
                'boundary' => [
                    'type' => 'envelope',
                    'coordinates' => [[30.2, 60.0], [30.5, 59.9]],
                ],
            ]),
        ]);
    }

    /** @test */
    public function it_can_filter_by_envelope_intersects(): void
    {
        $result = $this
            ->createElasticWizardWithFilters([
                'boundary' => [
                    'type' => 'envelope',
                    // lat 55.65-55.84 intersects Moscow Center (55.7-55.8) but not Moscow North (55.85-56.0)
                    'coordinates' => [[37.4, 55.84], [37.8, 55.65]],
                ],
            ], GeoModel::class)
            ->allowedFilters(GeoShapeFilter::make('boundary'))
            ->build()
            ->execute()
            ->models();

        // Should intersect with Moscow Center only
        $this->assertCount(1, $result);
        $this->assertEquals($this->models[0]->id, $result->first()->id);
    }

    /** @test */
    public function it_can_filter_with_explicit_intersects_relation(): void
    {
        $result = $this
            ->createElasticWizardWithFilters([
                'boundary' => [
                    'type' => 'envelope',
                    'coordinates' => [[37.4, 55.95], [37.8, 55.75]],
                ],
            ], GeoModel::class)
            ->allowedFilters(GeoShapeFilter::make('boundary')->relation('intersects'))
            ->build()
            ->execute()
            ->models();

        // Should intersect with both Moscow areas
        $this->assertCount(2, $result);
        $this->assertEqualsCanonicalizing(
            [$this->models[0]->id, $this->models[1]->id],
            $result->pluck('id')->all()
        );
    }

    /** @test */
    public function it_can_filter_with_disjoint_relation(): void
    {
        $result = $this
            ->createElasticWizardWithFilters([
                'boundary' => [
                    'type' => 'envelope',
                    // lat 55.65-55.84 intersects only Moscow Center (55.7-55.8)
                    'coordinates' => [[37.4, 55.84], [37.8, 55.65]],
                ],
            ], GeoModel::class)
            ->allowedFilters(GeoShapeFilter::make('boundary')->relation('disjoint'))
            ->build()
            ->execute()
            ->models();

        // Should return shapes that don't intersect (Moscow North + St Petersburg)
        $this->assertCount(2, $result);
        $this->assertEqualsCanonicalizing(
            [$this->models[1]->id, $this->models[2]->id],
            $result->pluck('id')->all()
        );
    }

    /** @test */
    public function it_can_filter_by_polygon(): void
    {
        $result = $this
            ->createElasticWizardWithFilters([
                'boundary' => [
                    'type' => 'polygon',
                    'coordinates' => [
                        [[37.4, 55.9], [37.8, 55.9], [37.8, 55.65], [37.4, 55.65], [37.4, 55.9]],
                    ],
                ],
            ], GeoModel::class)
            ->allowedFilters(GeoShapeFilter::make('boundary'))
            ->build()
            ->execute()
            ->models();

        // Should intersect with both Moscow areas
        $this->assertCount(2, $result);
    }

    /** @test */
    public function it_can_filter_by_point(): void
    {
        $result = $this
            ->createElasticWizardWithFilters([
                'boundary' => [
                    'type' => 'point',
                    'coordinates' => [37.6, 55.75],
                ],
            ], GeoModel::class)
            ->allowedFilters(GeoShapeFilter::make('boundary'))
            ->build()
            ->execute()
            ->models();

        // Point inside Moscow Center boundary
        $this->assertCount(1, $result);
        $this->assertEquals($this->models[0]->id, $result->first()->id);
    }

    /** @test */
    public function it_returns_no_results_for_non_intersecting_shape(): void
    {
        $result = $this
            ->createElasticWizardWithFilters([
                'boundary' => [
                    'type' => 'envelope',
                    'coordinates' => [[40.0, 50.0], [41.0, 49.0]],
                ],
            ], GeoModel::class)
            ->allowedFilters(GeoShapeFilter::make('boundary'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(0, $result);
    }

    /** @test */
    public function it_allows_empty_filter_value(): void
    {
        $result = $this
            ->createElasticWizardWithFilters([
                'boundary' => [],
            ], GeoModel::class)
            ->allowedFilters(GeoShapeFilter::make('boundary'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $result);
    }

    /** @test */
    public function it_throws_exception_for_invalid_envelope(): void
    {
        $this->expectException(InvalidGeoShapeValue::class);

        $this
            ->createElasticWizardWithFilters([
                'boundary' => [
                    'type' => 'envelope',
                    'coordinates' => [[37.4, 55.85]], // Only one point
                ],
            ], GeoModel::class)
            ->allowedFilters(GeoShapeFilter::make('boundary'))
            ->build();
    }

    /** @test */
    public function it_throws_exception_for_unknown_shape_type(): void
    {
        $this->expectException(InvalidGeoShapeValue::class);

        $this
            ->createElasticWizardWithFilters([
                'boundary' => [
                    'type' => 'linestring', // Not supported
                    'coordinates' => [[37.5, 55.7], [37.6, 55.8]],
                ],
            ], GeoModel::class)
            ->allowedFilters(GeoShapeFilter::make('boundary'))
            ->build();
    }

    /** @test */
    public function it_can_use_alias(): void
    {
        $result = $this
            ->createElasticWizardWithFilters([
                'area' => [
                    'type' => 'envelope',
                    // lat 55.65-55.84 intersects only Moscow Center (55.7-55.8)
                    'coordinates' => [[37.4, 55.84], [37.8, 55.65]],
                ],
            ], GeoModel::class)
            ->allowedFilters(GeoShapeFilter::make('boundary', 'area'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $result);
    }
}
