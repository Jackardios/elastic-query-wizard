<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Sorts;

use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\Sorts\GeoDistanceSort;
use Jackardios\ElasticQueryWizard\Tests\Concerns\AssertsCollectionSorting;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\GeoModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Jackardios\EloquentSpatial\Objects\Point;

/**
 * @group elastic
 * @group sort
 * @group elastic-sort
 */
class GeoDistanceSortTest extends TestCase
{
    use AssertsCollectionSorting;

    protected Collection $models;

    // Moscow center coordinates
    protected float $centerLat = 55.7558;
    protected float $centerLon = 37.6173;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = collect([
            // ~10km from center
            GeoModel::factory()->create([
                'name' => 'Near Location',
                'location' => new Point(37.6273, 55.8558),
            ]),
            // ~30km from center
            GeoModel::factory()->create([
                'name' => 'Medium Location',
                'location' => new Point(37.8173, 55.9558),
            ]),
            // ~50km from center
            GeoModel::factory()->create([
                'name' => 'Far Location',
                'location' => new Point(38.0173, 56.1558),
            ]),
        ]);
    }

    /** @test */
    public function it_can_sort_by_geo_distance_ascending(): void
    {
        $result = $this
            ->createElasticWizardWithSorts('distance', GeoModel::class)
            ->allowedSorts(
                GeoDistanceSort::make('location', $this->centerLat, $this->centerLon, 'distance')
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $result);
        // Nearest first
        $this->assertEquals([$this->models[0]->id, $this->models[1]->id, $this->models[2]->id], $result->pluck('id')->all());
    }

    /** @test */
    public function it_can_sort_by_geo_distance_descending(): void
    {
        $result = $this
            ->createElasticWizardWithSorts('-distance', GeoModel::class)
            ->allowedSorts(
                GeoDistanceSort::make('location', $this->centerLat, $this->centerLon, 'distance')
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $result);
        // Farthest first
        $this->assertEquals([$this->models[2]->id, $this->models[1]->id, $this->models[0]->id], $result->pluck('id')->all());
    }

    /** @test */
    public function it_can_use_different_units(): void
    {
        $result = $this
            ->createElasticWizardWithSorts('distance', GeoModel::class)
            ->allowedSorts(
                GeoDistanceSort::make('location', $this->centerLat, $this->centerLon, 'distance')
                    ->unit('m')
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $result);
        // Should still sort correctly
        $this->assertEquals([$this->models[0]->id, $this->models[1]->id, $this->models[2]->id], $result->pluck('id')->all());
    }

    /** @test */
    public function it_can_use_arc_distance_type(): void
    {
        $result = $this
            ->createElasticWizardWithSorts('distance', GeoModel::class)
            ->allowedSorts(
                GeoDistanceSort::make('location', $this->centerLat, $this->centerLon, 'distance')
                    ->distanceType('arc')
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $result);
        $this->assertEquals([$this->models[0]->id, $this->models[1]->id, $this->models[2]->id], $result->pluck('id')->all());
    }

    /** @test */
    public function it_can_use_plane_distance_type(): void
    {
        $result = $this
            ->createElasticWizardWithSorts('distance', GeoModel::class)
            ->allowedSorts(
                GeoDistanceSort::make('location', $this->centerLat, $this->centerLon, 'distance')
                    ->distanceType('plane')
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $result);
        // Plane is faster but less accurate for large distances
        $this->assertEquals([$this->models[0]->id, $this->models[1]->id, $this->models[2]->id], $result->pluck('id')->all());
    }

    /** @test */
    public function it_can_ignore_unmapped(): void
    {
        $result = $this
            ->createElasticWizardWithSorts('distance', GeoModel::class)
            ->allowedSorts(
                GeoDistanceSort::make('location', $this->centerLat, $this->centerLon, 'distance')
                    ->ignoreUnmapped()
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $result);
    }

    /** @test */
    public function it_can_sort_from_different_origin(): void
    {
        // Sort from Far Location's coordinates
        $result = $this
            ->createElasticWizardWithSorts('distance', GeoModel::class)
            ->allowedSorts(
                GeoDistanceSort::make('location', 56.1558, 38.0173, 'distance')
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $result);
        // Far location should now be first (nearest to origin)
        $this->assertEquals($this->models[2]->id, $result->first()->id);
    }
}
