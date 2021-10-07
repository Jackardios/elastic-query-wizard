<?php

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoBoundingBoxValue;
use Jackardios\ElasticQueryWizard\Handlers\Filters\GeoBoundingBoxFilter;
use Jackardios\ElasticQueryWizard\Tests\App\Models\GeoModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * @group elastic
 * @group filter
 * @group elastic-filter
 */
class GeoBoundingBoxFilterTest extends TestCase
{
    /** @var Collection */
    protected $models;

    public function setUp(): void
    {
        parent::setUp();

        $this->models = factory(GeoModel::class, 5)->create();
    }

    /** @test */
    public function it_throws_an_exception_when_invalid_bbox_provided(): void
    {
        $this->expectException(InvalidGeoBoundingBoxValue::class);
        $this
            ->createQueryFromFilterRequest([
                'bbox' => '59.70658123789505,29.8431393959961,60.12821910231846'
            ])
            ->setAllowedFilters(new GeoBoundingBoxFilter('location', 'bbox'))
            ->build();
    }

    /** @test */
    public function it_can_filter_results_by_geo_bounding_box_property(): void
    {
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(59.933237, 30.3694531)]);
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(59.973454, 30.5493402)]);
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(59.706582, 30.1243467)]);
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(60.103454, 29.8745233)]);
        factory(GeoModel::class)->create(['location' => new Point(61.973454, 30.5493402)]);

        $modelsResult = $this
            ->createQueryFromFilterRequest([
                'bbox' => '59.70658123789505,29.8431393959961,60.12821910231846,30.76667760400391',
            ])
            ->setAllowedFilters(new GeoBoundingBoxFilter('location', 'bbox'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(4, $modelsResult);
        $this->assertEqualsCanonicalizing(
            $modelsResult->pluck('id')->toArray(),
            array_map(static fn($model) => $model->id, $expectedModels)
        );
    }

    /** @test */
    public function it_should_apply_a_default_filter_value_if_nothing_in_request(): void
    {
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(59.933237, 30.3694531)]);
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(59.973454, 30.5493402)]);
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(59.706582, 30.1243467)]);
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(60.103454, 29.8745233)]);
        factory(GeoModel::class)->create(['location' => new Point(61.973454, 30.5493402)]);

        $modelsResult = $this
            ->createQueryFromFilterRequest([])
            ->setAllowedFilters(
                (new GeoBoundingBoxFilter('location', 'bbox'))
                    ->default([59.70658123789505,29.8431393959961,60.12821910231846,30.76667760400391])
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(4, $modelsResult);
        $this->assertEqualsCanonicalizing(
            $modelsResult->pluck('id')->toArray(),
            array_map(static fn($model) => $model->id, $expectedModels)
        );
    }

    /** @test */
    public function it_does_not_apply_default_filter_when_filter_exists_and_default_is_set(): void
    {
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(59.933237, 30.3694531)]);
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(59.973454, 30.5493402)]);
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(59.706582, 30.1243467)]);
        $expectedModels[] = factory(GeoModel::class)->create(['location' => new Point(60.103454, 29.8745233)]);
        factory(GeoModel::class)->create(['location' => new Point(61.973454, 30.5493402)]);

        $modelsResult = $this
            ->createQueryFromFilterRequest([
                'bbox' => '59.70658123789505,29.8431393959961,60.12821910231846,30.76667760400391'
            ])
            ->setAllowedFilters(
                (new GeoBoundingBoxFilter('location', 'bbox'))
                    ->default([55.105673,36.461995,56.056992,38.309071])
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(4, $modelsResult);
        $this->assertEqualsCanonicalizing(
            $modelsResult->pluck('id')->toArray(),
            array_map(static fn($model) => $model->id, $expectedModels)
        );
    }

    protected function createQueryFromFilterRequest(array $filters): ElasticQueryWizard
    {
        $request = new Request([
            'filter' => $filters,
        ]);

        return ElasticQueryWizard::for(GeoModel::class, $request);
    }
}
